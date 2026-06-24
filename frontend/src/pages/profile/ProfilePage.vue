<template>
  <q-page padding class="profile-page">
    <section class="profile-shell">
      <header class="profile-header">
        <div class="profile-header__identity">
          <q-avatar size="58px" color="primary" text-color="white">
            {{ initials }}
          </q-avatar>
          <div>
            <div class="page-kicker">Cuenta institucional</div>
            <h1 class="profile-title">Mi perfil</h1>
            <p class="profile-subtitle">Actualiza tus datos basicos de contacto y perfil docente.</p>
          </div>
        </div>
        <q-btn outline color="primary" icon="lock_reset" label="Cambiar contrasena" no-caps to="/change-password" />
      </header>

      <q-separator />

      <q-form class="profile-form" @submit.prevent="submit">
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-6">
            <q-input
              v-model="form.name"
              outlined
              label="Nombre completo"
              :rules="[val => !!val || 'El nombre es obligatorio']"
            >
              <template #prepend>
                <q-icon name="person" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-6">
            <q-input
              v-model="form.email"
              outlined
              type="email"
              label="Correo de acceso"
              :rules="[val => !!val || 'El correo es obligatorio']"
            >
              <template #prepend>
                <q-icon name="mail" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-6">
            <q-input v-model="form.phone" outlined label="Telefono">
              <template #prepend>
                <q-icon name="phone" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-6">
            <q-input v-model="form.highest_degree" outlined label="Grado academico">
              <template #prepend>
                <q-icon name="workspace_premium" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-6">
            <q-input v-model="form.specialty" outlined label="Especialidad">
              <template #prepend>
                <q-icon name="engineering" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-6">
            <q-input v-model="form.employment_type" outlined label="Tipo de vinculacion">
              <template #prepend>
                <q-icon name="badge" />
              </template>
            </q-input>
          </div>
        </div>

        <div class="profile-actions">
          <q-btn flat no-caps color="grey-8" label="Cancelar" @click="$router.back()" />
          <q-btn unelevated no-caps color="primary" icon="save" label="Guardar perfil" type="submit" :loading="saving" />
        </div>
      </q-form>
    </section>
  </q-page>
</template>

<script>
import { getStoredUser, updateStoredUser } from 'src/utils/auth'

export default {
  name: 'ProfilePage',

  data () {
    const user = getStoredUser()
    const teacher = user.teacher || {}

    return {
      loading: false,
      saving: false,
      currentUser: user,
      form: {
        name: user.name || '',
        email: user.email || '',
        phone: teacher.phone || '',
        highest_degree: teacher.highest_degree || '',
        specialty: teacher.specialty || '',
        employment_type: teacher.employment_type || ''
      }
    }
  },

  computed: {
    initials () {
      return (this.form.name || 'Usuario')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part.charAt(0))
        .join('')
        .toUpperCase()
    }
  },

  created () {
    this.loadProfile()
  },

  methods: {
    async loadProfile () {
      this.loading = true

      try {
        const response = await this.$api.get('/profile')
        this.currentUser = response.data.user
        const teacher = response.data.teacher || response.data.user?.teacher || {}
        this.form = {
          name: response.data.user.name || '',
          email: response.data.user.email || '',
          phone: teacher.phone || '',
          highest_degree: teacher.highest_degree || '',
          specialty: teacher.specialty || '',
          employment_type: teacher.employment_type || ''
        }
        updateStoredUser(response.data.user)
      } catch (error) {
        this.$q.notify({
          type: 'negative',
          icon: 'error',
          message: 'No se pudo cargar tu perfil'
        })
      } finally {
        this.loading = false
      }
    },

    async submit () {
      this.saving = true

      try {
        const response = await this.$api.put('/profile', this.form)
        updateStoredUser(response.data.user)
        this.currentUser = response.data.user
        this.$q.notify({
          type: 'positive',
          icon: 'check_circle',
          message: 'Perfil actualizado correctamente'
        })
      } catch (error) {
        const errors = error.response?.data?.errors || {}
        const firstError = Object.values(errors)[0]?.[0]
        this.$q.notify({
          type: 'negative',
          icon: 'error',
          message: firstError || error.response?.data?.message || 'No se pudo actualizar el perfil'
        })
      } finally {
        this.saving = false
      }
    }
  }
}
</script>

<style scoped>
.profile-page {
  display: flex;
  justify-content: center;
  background: #f3f7fb;
}

.profile-shell {
  width: min(980px, 100%);
  margin-top: 20px;
  border: 1px solid #dde6ef;
  border-radius: 14px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.profile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(135deg, rgba(11, 79, 122, 0.07), rgba(255, 255, 255, 0));
}

.profile-header__identity {
  display: flex;
  align-items: center;
  gap: 16px;
}

.profile-title {
  margin: 0;
  color: #172234;
  font-size: 1.55rem;
  font-weight: 900;
  line-height: 1.15;
}

.profile-subtitle {
  margin: 6px 0 0;
  color: #667085;
  line-height: 1.5;
}

.profile-form {
  padding: 24px;
}

.profile-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 22px;
}

@media (max-width: 700px) {
  .profile-header {
    align-items: stretch;
    flex-direction: column;
  }

  .profile-header .q-btn {
    width: 100%;
  }
}
</style>
