<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">Programas</div>
    <q-table :rows="rows" :columns="columns" row-key="id" :loading="loading" />
  </q-page>
</template>

<script>
export default {
  name: 'ProgramsPage',

  data () {
    return {
      loading: false,
      rows: [],
      columns: [
        { name: 'name', label: 'Programa', field: 'name', align: 'left', sortable: true },
        { name: 'code', label: 'Codigo', field: 'code', align: 'left', sortable: true },
        { name: 'faculty', label: 'Facultad', field: row => row.faculty ? row.faculty.name : '', align: 'left' },
        { name: 'modality', label: 'Modalidad', field: 'modality', align: 'left' }
      ]
    }
  },

  created () {
    this.loadPrograms()
  },

  methods: {
    async loadPrograms () {
      this.loading = true
      try {
        const response = await this.$api.get('/programs')
        this.rows = response.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los programas' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
