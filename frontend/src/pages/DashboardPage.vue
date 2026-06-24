<template>
  <q-page padding class="app-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Panel de control</div>
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">Avance general de acreditacion por criterio y estado.</div>
      </div>
      <q-btn icon="refresh" color="primary" flat @click="loadData">
        <q-tooltip>Actualizar</q-tooltip>
      </q-btn>
    </div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div v-for="card in cards" :key="card.label" class="col-12 col-sm-6 col-md">
        <q-card flat bordered class="metric-card">
          <q-card-section class="row items-center no-wrap">
            <q-avatar :color="card.color" text-color="white" :icon="card.icon" size="42px" />
            <div class="q-ml-md">
              <div class="metric-label">{{ card.label }}</div>
              <div class="metric-value">{{ card.value }}</div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card flat bordered class="q-mb-lg progress-panel">
      <q-card-section>
        <div class="row items-center q-col-gutter-md">
          <div class="col-12 col-md-3">
            <div class="text-subtitle2 text-grey-8">Avance total</div>
            <div class="progress-number">{{ summary.progress || 0 }}%</div>
          </div>
          <div class="col-12 col-md-9">
            <q-linear-progress :value="(summary.progress || 0) / 100" size="18px" rounded color="positive" track-color="grey-3" />
            <div class="row justify-between text-caption text-grey-7 q-mt-sm">
              <span>{{ summary.validated_or_more || 0 }} evidencias completadas</span>
              <span>{{ summary.total || 0 }} tareas requeridas</span>
            </div>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-table :rows="criteria" :columns="columns" row-key="id" :loading="loading" title="Avance por criterio" flat>
        <template v-slot:body-cell-progress="props">
          <q-td :props="props">
            <q-linear-progress :value="props.row.progress / 100" rounded size="12px" color="primary" track-color="grey-3" />
            <div class="text-caption text-grey-7 q-mt-xs">{{ props.row.progress }}%</div>
          </q-td>
        </template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script>
export default {
  name: 'DashboardPage',

  data () {
    return {
      loading: false,
      summary: {
        total: 0,
        pending: 0,
        observed: 0,
        validated: 0,
        approved: 0,
        progress: 0
      },
      criteria: [],
      columns: [
        { name: 'code', label: 'Codigo', field: 'code', align: 'left', sortable: true },
        { name: 'name', label: 'Criterio', field: 'name', align: 'left', sortable: true },
        { name: 'total', label: 'Total', field: 'total', align: 'center' },
        { name: 'completed', label: 'Completas', field: 'completed', align: 'center' },
        { name: 'progress', label: 'Avance', field: 'progress', align: 'left' }
      ]
    }
  },

  computed: {
    cards () {
      return [
        { label: 'Total', value: this.summary.total || 0, icon: 'inventory_2', color: 'primary' },
        { label: 'Pendientes', value: this.summary.pending || 0, icon: 'schedule', color: 'blue-grey' },
        { label: 'Observadas', value: this.summary.observed || 0, icon: 'report_problem', color: 'negative' },
        { label: 'Validadas', value: this.summary.validated || 0, icon: 'verified', color: 'positive' },
        { label: 'Aprobadas', value: this.summary.approved || 0, icon: 'task_alt', color: 'green' }
      ]
    }
  },

  created () {
    this.loadData()
  },

  methods: {
    async loadData () {
      this.loading = true
      try {
        const [summaryResponse, criteriaResponse] = await Promise.all([
          this.$api.get('/dashboard/summary'),
          this.$api.get('/dashboard/progress-by-criterion')
        ])
        this.summary = summaryResponse.data
        this.criteria = criteriaResponse.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo cargar el dashboard' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
