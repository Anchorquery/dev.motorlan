import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteLocationNormalized, RouteRecordRaw } from 'vue-router'

import { createRouter, createWebHistory } from 'vue-router/auto'

// Base dinámica desde WordPress (soporta WPML y múltiples páginas)
const getVueBase = (): string => {
  return (window as unknown as { wpData?: { vue_base?: string } }).wpData?.vue_base || '/'
}

import { redirects, routes } from './additional-routes'
import { setupGuards } from './guards'

function recursiveLayouts(route: RouteRecordRaw): RouteRecordRaw {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(getVueBase()),
  scrollBehavior(to: RouteLocationNormalized) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: (pages: RouteRecordRaw[]) => [
    ...redirects,
    ...[
      ...pages,
      ...routes,
    ].map(route => recursiveLayouts(route)),
  ],
})

setupGuards(router as any)

export { router }

export default function (app: App) {
  app.use(router)
}
