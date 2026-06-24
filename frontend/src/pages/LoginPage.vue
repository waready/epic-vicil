<template>
  <div class="login-page">
    <div class="login-shell">
      <section class="login-context">
        <div class="login-logo-row">
          <img :src="unapLogo" alt="UNAP Puno" class="login-seal" />
          <img :src="civilLogo" alt="Escuela Profesional de Ingenieria Civil" class="login-civil-logo" />
        </div>
        <div class="login-kicker">UNAP Puno Civil</div>
        <h1>Gestion de acreditacion y evidencias academicas</h1>
        <p>Plataforma institucional para seguimiento ICACIT, control documental, revision de evidencias y exportacion ordenada por criterios.</p>
        <div class="login-stats">
          <div>
            <strong>ICACIT</strong>
            <span>Modelo inicial</span>
          </div>
          <div>
            <strong>2026</strong>
            <span>Ciclo seed</span>
          </div>
          <div>
            <strong>11</strong>
            <span>Criterios base</span>
          </div>
        </div>
      </section>

      <q-card class="login-card" flat bordered>
        <q-card-section>
          <div class="text-h6">Acceso institucional</div>
          <div class="text-body2 text-grey-7">Ingresa con tu cuenta asignada.</div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <q-form @submit.prevent="login">
            <q-input v-model="form.email" label="Correo institucional" type="email" outlined dense class="q-mb-md" autocomplete="email">
              <template v-slot:prepend>
                <q-icon name="mail" />
              </template>
            </q-input>
            <q-input v-model="form.password" label="Contrasena" type="password" outlined dense class="q-mb-md" autocomplete="current-password">
              <template v-slot:prepend>
                <q-icon name="lock" />
              </template>
            </q-input>

            <q-btn type="submit" icon="login" label="Ingresar al sistema" color="primary" unelevated class="full-width login-submit" :loading="loading" />
          </q-form>
        </q-card-section>
      </q-card>
    </div>
  </div>
</template>

<script>
import { defaultPathFor, setAuthSession } from 'src/utils/auth'

export default {
  name: 'LoginPage',

  data () {
    return {
      loading: false,
      unapLogo: new URL('../assets/brand/unap-footer.png', import.meta.url).href,
      civilLogo: new URL('../assets/brand/ep-logo-civil.png', import.meta.url).href,
      form: {
        email: 'admin@acreditacion.local',
        password: 'password'
      }
    }
  },

  methods: {
    async login () {
      this.loading = true

      try {
        const response = await this.$api.post('/auth/login', this.form)
        setAuthSession({
          token: response.data.token,
          user: response.data.user,
          expiresAt: response.data.expires_at
        })

        const mustChange = Boolean(response.data.user?.must_change_password)
        this.$q.notify({
          type: mustChange ? 'warning' : 'positive',
          message: mustChange ? 'Actualiza tu contrasena para continuar' : 'Bienvenido'
        })
        this.$router.push(defaultPathFor(response.data.user))
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'Credenciales incorrectas' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
