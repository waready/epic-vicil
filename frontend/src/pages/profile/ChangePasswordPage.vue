<template>
  <q-page padding class="profile-page">
    <section class="security-card">
      <div class="security-card__header">
        <q-avatar color="blue-1" text-color="primary" icon="lock_reset" size="54px" />
        <div>
          <div class="page-kicker">Seguridad de cuenta</div>
          <h1 class="security-card__title">Cambiar contrasena</h1>
          <p class="security-card__subtitle">
            Usa una contrasena personal antes de continuar usando el sistema.
          </p>
        </div>
      </div>

      <q-banner v-if="currentUser.must_change_password" class="security-warning" rounded>
        <template #avatar>
          <q-icon name="priority_high" color="orange-9" />
        </template>
        Esta cuenta usa una contrasena temporal. Debes cambiarla para acceder al resto del sistema.
      </q-banner>

      <q-form class="q-mt-lg" @submit.prevent="submit">
        <q-input
          v-model="form.current_password"
          outlined
          :type="showCurrent ? 'text' : 'password'"
          label="Contrasena actual"
          autocomplete="current-password"
          :rules="[val => !!val || 'Ingresa tu contrasena actual']"
        >
          <template #prepend>
            <q-icon name="lock" />
          </template>
          <template #append>
            <q-btn flat dense round :icon="showCurrent ? 'visibility_off' : 'visibility'" @click="showCurrent = !showCurrent" />
          </template>
        </q-input>

        <q-input
          v-model="form.password"
          outlined
          class="q-mt-md"
          :type="showNew ? 'text' : 'password'"
          label="Nueva contrasena"
          autocomplete="new-password"
          hint="Minimo 8 caracteres, con letras y numeros."
          :rules="[val => !!val || 'Ingresa una nueva contrasena', val => val.length >= 8 || 'Minimo 8 caracteres']"
        >
          <template #prepend>
            <q-icon name="vpn_key" />
          </template>
          <template #append>
            <q-btn flat dense round :icon="showNew ? 'visibility_off' : 'visibility'" @click="showNew = !showNew" />
          </template>
        </q-input>

        <q-input
          v-model="form.password_confirmation"
          outlined
          class="q-mt-md"
          :type="showConfirm ? 'text' : 'password'"
          label="Confirmar nueva contrasena"
          autocomplete="new-password"
          :rules="[val => !!val || 'Confirma tu contrasena', val => val === form.password || 'Las contrasenas no coinciden']"
        >
          <template #prepend>
            <q-icon name="check_circle" />
          </template>
          <template #append>
            <q-btn flat dense round :icon="showConfirm ? 'visibility_off' : 'visibility'" @click="showConfirm = !showConfirm" />
          </template>
        </q-input>

        <div class="security-actions">
          <q-btn
            v-if="!currentUser.must_change_password"
            flat
            no-caps
            color="grey-8"
            label="Volver"
            @click="$router.back()"
          />
          <q-space />
          <q-btn
            unelevated
            no-caps
            color="primary"
            icon="save"
            label="Guardar contrasena"
            type="submit"
            :loading="saving"
          />
        </div>
      </q-form>
    </section>
  </q-page>
</template>

<script>
import { defaultPathFor, getStoredUser, updateStoredUser } from 'src/utils/auth'

export default {
  name: 'ChangePasswordPage',

  data () {
    return {
      saving: false,
      showCurrent: false,
      showNew: false,
      showConfirm: false,
      currentUser: getStoredUser(),
      form: {
        current_password: '',
        password: '',
        password_confirmation: ''
      }
    }
  },

  methods: {
    async submit () {
      this.saving = true

      try {
        const response = await this.$api.post('/auth/change-password', this.form)
        updateStoredUser(response.data.user)
        this.currentUser = response.data.user
        this.$q.notify({
          type: 'positive',
          icon: 'check_circle',
          message: 'Contrasena actualizada correctamente'
        })
        this.$router.push(defaultPathFor(response.data.user))
      } catch (error) {
        const errors = error.response?.data?.errors || {}
        const firstError = Object.values(errors)[0]?.[0]
        this.$q.notify({
          type: 'negative',
          icon: 'error',
          message: firstError || error.response?.data?.message || 'No se pudo cambiar la contrasena'
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

.security-card {
  width: min(720px, 100%);
  margin-top: 24px;
  border: 1px solid #dde6ef;
  border-radius: 14px;
  padding: 26px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.security-card__header {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.security-card__title {
  margin: 0;
  color: #172234;
  font-size: 1.55rem;
  font-weight: 900;
  line-height: 1.15;
}

.security-card__subtitle {
  margin: 6px 0 0;
  color: #667085;
  line-height: 1.5;
}

.security-warning {
  margin-top: 18px;
  border: 1px solid #fde7b2;
  background: #fff8e1;
  color: #5f4200;
}

.security-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 22px;
}
</style>
