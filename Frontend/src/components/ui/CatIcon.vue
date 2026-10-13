<template>
  <component :is="ICONOS[name] || PhForkKnife" v-bind="$attrs" />
</template>

<script>
import {
  PhForkKnife, PhEgg, PhCoffee, PhBowlFood, PhCake, PhMartini, PhHamburger, PhPizza, PhBeerStein, PhWine,
  PhCookie, PhIceCream, PhLeaf, PhFish, PhBread, PhCarrot, PhPepper, PhCoffeeBean, PhOrange, PhBowlSteam,
} from '@phosphor-icons/vue'

// Íconos disponibles para categorías (se guardan por nombre en la base de datos)
export const ICONOS = {
  ForkKnife: PhForkKnife, Egg: PhEgg, Coffee: PhCoffee, Bowl: PhBowlFood, Cake: PhCake, Martini: PhMartini,
  Hamburger: PhHamburger, Pizza: PhPizza, BeerStein: PhBeerStein, Wine: PhWine, Cookie: PhCookie, IceCream: PhIceCream,
  Leaf: PhLeaf, Fish: PhFish, Bread: PhBread, Carrot: PhCarrot, Pepper: PhPepper, CoffeeBean: PhCoffeeBean,
  Orange: PhOrange, BowlSteam: PhBowlSteam,
}
export default {
  inheritAttrs: false,
  props: { name: String },
  setup: () => ({ ICONOS, PhForkKnife }),
}
</script>
