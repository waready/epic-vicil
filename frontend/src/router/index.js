import { route } from 'quasar/wrappers'
import { createRouter, createMemoryHistory, createWebHistory, createWebHashHistory } from 'vue-router'
import routes from './routes'
import {
  defaultPathFor,
  getAuthToken,
  getStoredUser,
  isTeacherOnly,
  permissionNames,
  requiresPasswordChange
} from 'src/utils/auth'

export default route(function () {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : (process.env.VUE_ROUTER_MODE === 'history' ? createWebHistory : createWebHashHistory)

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,
    history: createHistory(process.env.VUE_ROUTER_BASE)
  })

  Router.beforeEach((to, from, next) => {
    const publicPages = ['/login']
    const authRequired = !publicPages.includes(to.path)
    const token = getAuthToken()
    const user = getStoredUser()

    if (authRequired && !token) {
      return next('/login')
    }

    if (to.path === '/login' && token) {
      return next(defaultPathFor(user))
    }

    if (authRequired && token) {
      if (requiresPasswordChange(user) && to.path !== '/change-password') {
        return next('/change-password')
      }

      const permissions = permissionNames(user)

      if (to.meta.blockTeacherOnly && isTeacherOnly(user)) {
        return next(defaultPathFor(user))
      }

      if (to.meta.permission && !permissions.includes(to.meta.permission)) {
        return next(defaultPathFor(user))
      }
    }

    next()
  })

  return Router
})
