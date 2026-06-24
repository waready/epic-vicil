<template>
  <q-layout view="lHh Lpr lFf" class="admin-shell">
    <q-header class="admin-header">
      <q-toolbar class="admin-toolbar">
        <q-btn flat dense round icon="menu" @click="drawer = !drawer" />
        <img :src="unapLogo" alt="UNAP" class="header-logo lt-md" />
        <q-toolbar-title>
          <div class="app-title">Sistema de Acreditacion ICACIT</div>
          <div class="app-subtitle">UNAP Puno - Escuela Profesional de Ingenieria Civil</div>
        </q-toolbar-title>
        <q-btn flat round icon="notifications">
          <q-tooltip>Notificaciones</q-tooltip>
        </q-btn>
        <q-separator vertical dark inset class="q-mx-sm" />
        <div class="user-chip gt-xs">
          <q-avatar size="32px" color="white" text-color="primary">{{ initials }}</q-avatar>
          <div>
            <div class="user-name">{{ currentUser.name || 'Usuario' }}</div>
            <div class="user-role">{{ primaryRole }}</div>
          </div>
        </div>
        <q-btn flat round icon="account_circle">
          <q-tooltip>Cuenta</q-tooltip>
          <q-menu anchor="bottom right" self="top right">
            <q-list style="min-width: 220px">
              <q-item clickable v-close-popup to="/profile">
                <q-item-section avatar>
                  <q-icon name="person" />
                </q-item-section>
                <q-item-section>Mi perfil</q-item-section>
              </q-item>
              <q-item clickable v-close-popup to="/change-password">
                <q-item-section avatar>
                  <q-icon name="lock_reset" />
                </q-item-section>
                <q-item-section>Cambiar contrasena</q-item-section>
              </q-item>
              <q-separator />
              <q-item clickable v-close-popup @click="logout">
                <q-item-section avatar>
                  <q-icon name="logout" />
                </q-item-section>
                <q-item-section>Salir</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawer" show-if-above  class="admin-drawer">
      <div class="drawer-brand">
        <img :src="civilLogo" alt="Escuela Profesional de Ingenieria Civil" class="brand-logo" />
        <div>
          <div class="brand-caption">Acreditacion y evidencias</div>
        </div>
      </div>

      <div class="drawer-scroll">
        <q-list padding class="drawer-menu">
          <q-item-label header class="drawer-section">Menu principal</q-item-label>

          <q-expansion-item
            v-for="group in visibleMenuGroups"
            :key="group.label"
            :icon="group.icon"
            :label="group.label"
            default-opened
            expand-icon="keyboard_arrow_down"
            class="menu-group"
          >
            <q-item
              v-for="item in group.items"
              :key="item.to"
              clickable
              :to="item.to"
              exact
              active-class="menu-active"
              class="menu-item"
            >
              <q-item-section avatar>
                <q-icon :name="item.icon" />
              </q-item-section>
              <q-item-section>{{ item.label }}</q-item-section>
            </q-item>
          </q-expansion-item>
        </q-list>
      </div>

      <div class="drawer-footer">
        <div class="drawer-footer-icon">
          <q-icon name="verified" />
        </div>
        <div>
          <div class="text-caption">Modelo activo</div>
          <div class="text-weight-medium">{{ activeCycleLabel }}</div>
        </div>
      </div>
    </q-drawer>

    <q-page-container class="admin-page-container">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>
import {
  clearAuthSession,
  getStoredUser,
  isTeacherOnly,
  permissionNames
} from 'src/utils/auth'

export default {
  name: 'MainLayout',

  data () {
    return {
      drawer: true,
      currentUserState: getStoredUser(),
      civilLogo: new URL('../assets/brand/ep-logo-civil.png', import.meta.url).href,
      unapLogo: new URL('../assets/brand/unap-footer.png', import.meta.url).href,
      activeCycle: null,
      menuGroups: [
        {
          label: 'Inicio',
          icon: 'space_dashboard',
          items: [
            { label: 'Dashboard', icon: 'dashboard', to: '/dashboard', permission: 'view.dashboard' }
          ]
        },
        {
          label: 'Evidencias',
          icon: 'folder_open',
          items: [
            { label: 'Repositorio', icon: 'inventory_2', to: '/evidences', permission: 'view.evidences', blockTeacherOnly: true },
            { label: 'Mis evidencias', icon: 'assignment_turned_in', to: '/my-evidences', permission: 'create.evidences' },
            { label: 'Subir evidencia', icon: 'upload_file', to: '/evidences/create', permission: 'create.evidences', blockTeacherOnly: true }
          ]
        },
        {
          label: 'Acreditacion',
          icon: 'fact_check',
          items: [
            { label: 'Criterios', icon: 'fact_check', to: '/criteria', permission: 'manage.accreditation', blockTeacherOnly: true },
            { label: 'Reportes', icon: 'query_stats', to: '/reports', permission: 'view.dashboard', blockTeacherOnly: true },
            { label: 'Exportaciones', icon: 'archive', to: '/exports', permission: 'export.evidences' }
          ]
        },
        {
          label: 'Administracion',
          icon: 'admin_panel_settings',
          items: [
            { label: 'Usuarios', icon: 'manage_accounts', to: '/users', permission: 'manage.catalogs' },
            { label: 'Instituciones', icon: 'corporate_fare', to: '/institutions', permission: 'manage.catalogs' },
            { label: 'Facultades', icon: 'account_balance', to: '/faculties', permission: 'manage.catalogs' },
            { label: 'Programas', icon: 'school', to: '/programs', permission: 'manage.catalogs' },
            { label: 'Planes', icon: 'menu_book', to: '/study-plans', permission: 'manage.catalogs' },
            { label: 'Cursos', icon: 'class', to: '/courses', permission: 'manage.catalogs' },
            { label: 'Carga docente', icon: 'event_note', to: '/course-offerings', permission: 'manage.catalogs' },
            { label: 'Docentes', icon: 'groups', to: '/teachers', permission: 'manage.catalogs' }
          ]
        }
      ]
    }
  },

  computed: {
    currentUser () {
      return this.currentUserState
    },

    primaryRole () {
      const role = (this.currentUser.roles || [])[0] || 'consulta'
      return role.replaceAll('_', ' ')
    },

    userPermissions () {
      return permissionNames(this.currentUser)
    },

    userRoles () {
      return this.currentUser.roles || []
    },

    isTeacherOnly () {
      return isTeacherOnly(this.currentUser)
    },

    visibleMenuGroups () {
      return this.menuGroups
        .map(group => ({
          ...group,
          items: group.items.filter(item => this.canSeeMenuItem(item))
        }))
        .filter(group => group.items.length > 0)
    },

    initials () {
      const name = this.currentUser.name || 'Usuario'
      return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part.charAt(0))
        .join('')
        .toUpperCase()
    },

    activeCycleLabel () {
      if (!this.activeCycle) return 'Sin ciclo activo'
      const term = this.activeCycle.term ? (this.activeCycle.term.code || this.activeCycle.term.name) : ''
      const model = this.activeCycle.model ? this.activeCycle.model.code : 'Modelo'
      return term ? `${model} ${term}` : this.activeCycle.name
    }
  },

  created () {
    window.addEventListener('auth:user-updated', this.syncCurrentUser)
    this.loadActiveCycle()
  },

  beforeUnmount () {
    window.removeEventListener('auth:user-updated', this.syncCurrentUser)
  },

  methods: {
    syncCurrentUser () {
      this.currentUserState = getStoredUser()
    },

    async loadActiveCycle () {
      try {
        const response = await this.$api.get('/accreditation-cycles', { params: { status: 'active' } })
        this.activeCycle = response.data && response.data.length ? response.data[0] : null
      } catch (error) {
        this.activeCycle = null
      }
    },

    canSeeMenuItem (item) {
      if (item.blockTeacherOnly && this.isTeacherOnly) return false
      if (item.permission && !this.userPermissions.includes(item.permission)) return false
      return true
    },

    async logout () {
      try {
        await this.$api.post('/auth/logout')
      } catch (error) {
        console.error(error)
      }

      clearAuthSession()
      this.$router.push('/login')
    }
  }
}
</script>
