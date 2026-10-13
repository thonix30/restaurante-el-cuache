<?php
namespace App\Core;

use App\Attributes\Route;
use App\Attributes\Authorize;
use App\Attributes\HasPrivilege;
use App\Attributes\PublicRoute;
use App\Utils\Auth;
use App\Utils\Response;
use ReflectionClass;
use ReflectionMethod;

class Router
{
    private $controllers = [];

    public function registerController(string $controllerClass)
    {
        $this->controllers[] = $controllerClass;
    }

    public function dispatch($method, $uri)
    {
        try {
            $this->route($method, $uri);
        } catch (HttpException $e) {
            Response::error($e->getMessage(), $e->status, $e->extra);
        } catch (\Throwable $e) {
            error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            Response::error('Ocurrió un error al procesar la solicitud.', 500);
        }
    }

    private function route($method, $uri)
    {
        // La carpeta del proyecto tiene espacios ("Restaurante el cuache"):
        // REQUEST_URI llega codificado (%20) y SCRIPT_NAME no, así que se
        // decodifica antes de comparar.
        $uri = rawurldecode(strtok($uri, '?'));

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        } else {
            // Petición reescrita desde Backend/.htaccess (sin /api/v1 en la URL)
            $backendDir = dirname($scriptDir, 2);
            if ($backendDir !== '/' && strpos($uri, $backendDir) === 0) {
                $uri = substr($uri, strlen($backendDir));
                $uri = preg_replace('@^/api/v1@', '', $uri);
            }
        }
        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        $uri = rtrim($uri, '/') ?: '/';

        $methodAllowedElsewhere = false;

        foreach ($this->controllers as $controllerClass) {
            $reflection = new ReflectionClass($controllerClass);
            $classAuth = $reflection->getAttributes(Authorize::class);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $methodRef) {
                foreach ($methodRef->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();

                    $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route->path);
                    $pattern = "@^" . $pattern . "$@D";

                    if (!preg_match($pattern, $uri, $matches)) continue;
                    if ($route->method !== $method) { $methodAllowedElsewhere = true; continue; }

                    array_shift($matches);

                    // Autorización: el atributo del método tiene prioridad sobre el de la clase
                    $isPublic = !empty($methodRef->getAttributes(PublicRoute::class));
                    $authAttrs = $methodRef->getAttributes(Authorize::class) ?: ($isPublic ? [] : $classAuth);

                    if (!empty($authAttrs)) {
                        $auth = $authAttrs[0]->newInstance();
                        Auth::requireRoles($auth->roles);
                    } else {
                        Auth::tryLoad(); // sesión opcional
                    }

                    $privAttrs = $methodRef->getAttributes(HasPrivilege::class);
                    if (!empty($privAttrs)) {
                        $priv = $privAttrs[0]->newInstance();
                        Auth::requirePrivilege($priv->privilege);
                    }

                    $controllerInstance = new $controllerClass();
                    call_user_func_array([$controllerInstance, $methodRef->getName()], $matches);
                    return;
                }
            }
        }

        if ($methodAllowedElsewhere) {
            Response::error('Método no permitido.', 405);
        }
        Response::error('Endpoint no encontrado.', 404);
    }
}
