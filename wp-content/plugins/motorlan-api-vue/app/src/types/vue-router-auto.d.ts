declare module 'vue-router/auto' {
	import type { Router, RouterOptions as VueRouterOptions, WebHistory, RouteRecordRaw } from 'vue-router'

	// Extend RouterOptions to include extendRoutes and make routes optional
	export interface RouterOptions extends Omit<VueRouterOptions, 'routes'> {
		routes?: RouteRecordRaw[]
		extendRoutes?: (routes: RouteRecordRaw[]) => RouteRecordRaw[] | Promise<RouteRecordRaw[]>
	}

	export function createRouter(options: RouterOptions): Router
	export function createWebHistory(base?: string): WebHistory
	export function createWebHashHistory(base?: string): WebHistory
}
