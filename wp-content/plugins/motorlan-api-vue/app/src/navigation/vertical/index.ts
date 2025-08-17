import type { VerticalNavItems } from '@layouts/types'
import appsAndPages from './apps-and-pages'
import dashboard from './dashboard'

export default [...dashboard, ...appsAndPages] as VerticalNavItems
