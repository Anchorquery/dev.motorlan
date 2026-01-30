declare module 'virtual:meta-layouts' {
	import { RouteRecordRaw } from 'vue-router'
	export function setupLayouts(routes: RouteRecordRaw[]): RouteRecordRaw[]
}
