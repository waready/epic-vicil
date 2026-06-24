<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">Reportes</div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-7">
        <q-table title="Avance por programa" :rows="programs" :columns="programColumns" row-key="id" :loading="loading">
          <template v-slot:body-cell-progress="props">
            <q-td :props="props">
              <q-linear-progress :value="props.row.progress / 100" rounded size="14px" color="primary" />
              <div class="text-caption">{{ props.row.progress }}%</div>
            </q-td>
          </template>
        </q-table>
      </div>
      <div class="col-12 col-md-5">
        <q-table title="Pendientes por responsable" :rows="pending" :columns="pendingColumns" row-key="id" :loading="loading" />
      </div>
    </div>
  </q-page>
</template>

<script>
export default {
  name: 'ReportsPage',

  data () {
    return {
      loading: false,
      programs: [],
      pending: [],
      programColumns: [
        { name: 'code', label: 'Codigo', field: 'code', align: 'left' },
        { name: 'name', label: 'Programa', field: 'name', align: 'left' },
        { name: 'total', label: 'Total', field: 'total', align: 'center' },
        { name: 'completed', label: 'Completas', field: 'completed', align: 'center' },
        { name: 'progress', label: 'Avance', field: 'progress', align: 'left' }
      ],
      pendingColumns: [
        { name: 'name', label: 'Responsable', field: 'name', align: 'left' },
        { name: 'total', label: 'Pendientes', field: 'total', align: 'center' }
      ]
    }
  },

  created () {
    this.loadReports()
  },

  methods: {
    async loadReports () {
      this.loading = true
      try {
        const [programs, pending] = await Promise.all([
          this.$api.get('/dashboard/progress-by-program'),
          this.$api.get('/dashboard/pending-by-teacher')
        ])
        this.programs = programs.data
        this.pending = pending.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los reportes' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
