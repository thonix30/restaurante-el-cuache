<?php
namespace App\DTOs;

class LoginDTO extends DTO
{
    public ?string $usuario;
    public string $password;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->usuario = $dto->str('usuario', 150);
        $dto->password = (string)($data['password'] ?? '');
        $dto->required('usuario', $dto->usuario, 'Escribe tu usuario.');
        $dto->required('password', $dto->password, 'Escribe tu contraseña.');
        $dto->check();
        return $dto;
    }
}
