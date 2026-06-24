const TOKEN_KEY = 'access_token'
const USER_KEY = 'user'
const EXPIRES_KEY = 'token_expires_at'

const privilegedRoles = [
  'super_admin',
  'admin_facultad',
  'director_programa',
  'coordinador_acreditacion',
  'comite_calidad',
  'responsable_laboratorio',
  'auditor_interno'
]

function storage () {
  return window.sessionStorage
}

export function clearLegacyAuth () {
  window.localStorage.removeItem(TOKEN_KEY)
  window.localStorage.removeItem(USER_KEY)
  window.localStorage.removeItem(EXPIRES_KEY)
}

export function setAuthSession ({ token, user, expiresAt }) {
  clearLegacyAuth()
  storage().setItem(TOKEN_KEY, token)
  storage().setItem(USER_KEY, JSON.stringify(user || {}))

  if (expiresAt) {
    storage().setItem(EXPIRES_KEY, expiresAt)
  } else {
    storage().removeItem(EXPIRES_KEY)
  }

  window.dispatchEvent(new CustomEvent('auth:user-updated'))
}

export function updateStoredUser (user) {
  storage().setItem(USER_KEY, JSON.stringify(user || {}))
  window.dispatchEvent(new CustomEvent('auth:user-updated'))
}

export function clearAuthSession () {
  storage().removeItem(TOKEN_KEY)
  storage().removeItem(USER_KEY)
  storage().removeItem(EXPIRES_KEY)
  clearLegacyAuth()
  window.dispatchEvent(new CustomEvent('auth:user-updated'))
}

export function getAuthToken () {
  const expiresAt = storage().getItem(EXPIRES_KEY)

  if (expiresAt && new Date(expiresAt).getTime() <= Date.now()) {
    clearAuthSession()
    return null
  }

  return storage().getItem(TOKEN_KEY)
}

export function getStoredUser () {
  try {
    return JSON.parse(storage().getItem(USER_KEY) || '{}')
  } catch (error) {
    return {}
  }
}

export function permissionNames (user = getStoredUser()) {
  return (user.permissions || []).map(permission =>
    typeof permission === 'string' ? permission : permission.name
  )
}

export function isTeacherOnly (user = getStoredUser()) {
  const roles = user.roles || []
  return roles.includes('docente') && !roles.some(role => privilegedRoles.includes(role))
}

export function requiresPasswordChange (user = getStoredUser()) {
  return Boolean(user && user.must_change_password)
}

export function defaultPathFor (user = getStoredUser()) {
  if (requiresPasswordChange(user)) {
    return '/change-password'
  }

  return isTeacherOnly(user) ? '/my-evidences' : '/dashboard'
}
