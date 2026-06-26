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

    <div class="row q-col-gutter-md q-mt-md">
      <div class="col-12">
        <q-table
          title="Cumplimiento por docente"
          :rows="teacherStatus"
          :columns="teacherColumns"
          row-key="user_id"
          :loading="loading"
          :rows-per-page-options="[10, 20, 50, 0]"
        >
          <template v-slot:body-cell-progress="props">
            <q-td :props="props">
              <q-linear-progress :value="props.row.progress / 100" rounded size="12px" color="positive" track-color="green-1" />
              <div class="text-caption">{{ props.row.progress }}%</div>
            </q-td>
          </template>

          <template v-slot:body-cell-last_submission_at="props">
            <q-td :props="props">
              {{ formatDate(props.row.last_submission_at) }}
            </q-td>
          </template>

          <template v-slot:body-cell-status_summary="props">
            <q-td :props="props">
              <q-chip v-if="props.row.missing > 0" dense color="orange-8" text-color="white">
                Faltan {{ props.row.missing }}
              </q-chip>
              <q-chip v-else dense color="positive" text-color="white">
                Completo
              </q-chip>
            </q-td>
          </template>
        </q-table>
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
      teacherStatus: [],
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
      ],
      teacherColumns: [
        { name: 'name', label: 'Docente', field: 'name', align: 'left', sortable: true },
        { name: 'email', label: 'Correo', field: 'email', align: 'left' },
        { name: 'total', label: 'Asignadas', field: 'total', align: 'center', sortable: true },
        { name: 'submitted', label: 'Enviadas', field: 'submitted', align: 'center', sortable: true },
        { name: 'missing', label: 'Faltantes', field: 'missing', align: 'center', sortable: true },
        { name: 'observed', label: 'Observadas', field: 'observed', align: 'center', sortable: true },
        { name: 'accepted', label: 'Validadas/Aprobadas', field: 'accepted', align: 'center', sortable: true },
        { name: 'progress', label: 'Avance envio', field: 'progress', align: 'left', sortable: true },
        { name: 'last_submission_at', label: 'Ultimo envio', field: 'last_submission_at', align: 'left' },
        { name: 'status_summary', label: 'Estado', field: 'missing', align: 'left' }
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
        const [programs, pending, teacherStatus] = await Promise.all([
          this.$api.get('/dashboard/progress-by-program'),
          this.$api.get('/dashboard/pending-by-teacher'),
          this.$api.get('/dashboard/teacher-evidence-status')
        ])
        this.programs = programs.data
        this.pending = pending.data
        this.teacherStatus = teacherStatus.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los reportes' })
      } finally {
        this.loading = false
      }
    },

    formatDate (value) {
      if (!value) return 'Sin envio'

      const date = new Date(value)
      if (Number.isNaN(date.getTime())) return 'Sin envio'

      return new Intl.DateTimeFormat('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      }).format(date)
    }
  }
}
</script>
