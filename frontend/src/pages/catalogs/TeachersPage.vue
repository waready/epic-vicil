<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h5">Docentes</div>
      <q-btn flat icon="refresh" color="primary" @click="loadTeachers">
        <q-tooltip>Actualizar</q-tooltip>
      </q-btn>
    </div>
    <q-table :rows="rows" :columns="columns" row-key="id" :loading="loading" />
  </q-page>
</template>

<script>
export default {
  name: 'TeachersPage',

  data () {
    return {
      loading: false,
      rows: [],
      columns: [
        { name: 'name', label: 'Docente', field: row => `${row.last_name}, ${row.first_name}`, align: 'left', sortable: true },
        { name: 'email', label: 'Correo', field: 'email', align: 'left' },
        { name: 'degree', label: 'Grado', field: 'highest_degree', align: 'left' },
        { name: 'specialty', label: 'Especialidad', field: 'specialty', align: 'left' },
        { name: 'user', label: 'Usuario', field: row => row.user ? row.user.email : '', align: 'left' }
      ]
    }
  },

  created () {
    this.loadTeachers()
  },

  methods: {
    async loadTeachers () {
      this.loading = true
      try {
        const response = await this.$api.get('/teachers')
        this.rows = response.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los docentes' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
