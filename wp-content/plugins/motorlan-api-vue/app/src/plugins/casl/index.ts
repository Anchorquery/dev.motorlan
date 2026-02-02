import type { App } from 'vue'
import { createMongoAbility } from '@casl/ability'
import { abilitiesPlugin } from '@casl/vue'
import type { Rule } from './ability'

export default function (app: App) {
  // Get initial abilities from WordPress bootstrap data
  const wpUserData = (window as any)?.wpData?.user_data
  const isAdmin = wpUserData?.user?.is_admin ?? false
  const isLoggedIn = wpUserData?.is_logged_in ?? false

  // Set default abilities based on user role
  let initialRules: Rule[] = []

  if (isLoggedIn) {
    if (isAdmin) {
      initialRules = [{ action: 'manage', subject: 'all' }]
    }
    else {
      initialRules = [{ action: 'read', subject: 'all' }]
    }
  }

  const initialAbility = createMongoAbility(initialRules)

  app.use(abilitiesPlugin, initialAbility, {
    useGlobalProperties: true,
  })
}
