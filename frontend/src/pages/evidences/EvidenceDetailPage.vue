<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-md">
      <q-btn flat icon="arrow_back" label="Volver" to="/evidences" />
      <div class="q-gutter-sm">
        <q-btn v-if="can('review.evidences')" color="negative" outline icon="feedback" label="Observar" @click="openReviewDialog('observe')" />
        <q-btn v-if="can('validate.evidences')" color="positive" outline icon="verified" label="Validar" @click="openReviewDialog('validate')" />
        <q-btn v-if="can('approve.evidences')" color="green" outline icon="task_alt" label="Aprobar" @click="openReviewDialog('approve')" />
        <q-btn v-if="can('create.evidences')" color="primary" icon="upload_file" label="Nueva version" @click="openVersionDialog" />
      </div>
    </div>

    <q-card v-if="evidence" class="q-mb-md">
      <q-card-section>
        <div class="row items-start justify-between q-col-gutter-md">
          <div class="col-12 col-md-9">
            <div class="text-h5">{{ evidence.title }}</div>
            <div class="text-grey-7">{{ evidence.requirement ? evidence.requirement.name : '' }}</div>
          </div>
          <div class="col-12 col-md-3 text-md-right">
            <q-badge :color="statusColor(evidence.status)" class="q-pa-sm">{{ evidence.status }}</q-badge>
          </div>
        </div>
      </q-card-section>
      <q-separator />
      <q-card-section>
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-4"><b>Programa:</b> {{ evidence.program ? evidence.program.name : '' }}</div>
          <div class="col-12 col-md-4"><b>Ciclo:</b> {{ evidence.cycle ? evidence.cycle.name : '' }}</div>
          <div class="col-12 col-md-4"><b>Criterio:</b> {{ evidence.criterion ? evidence.criterion.code + ' - ' + evidence.criterion.name : '' }}</div>
          <div class="col-12 col-md-4"><b>Version:</b> {{ evidence.version_number }}</div>
          <div class="col-12 col-md-4"><b>Docente:</b> {{ evidence.teacher ? evidence.teacher.first_name + ' ' + evidence.teacher.last_name : 'No asignado' }}</div>
          <div class="col-12 col-md-4"><b>Fecha:</b> {{ formatDate(evidence.submitted_at) }}</div>
        </div>
        <p class="q-mt-md">{{ evidence.description }}</p>
      </q-card-section>
      <q-card-actions v-if="evidence.current_file" align="left">
        <q-btn color="primary" icon="description" label="Archivo actual" :href="evidence.current_file.url" target="_blank" :disable="!evidence.current_file.url" />
      </q-card-actions>
    </q-card>

    <div class="row q-col-gutter-md" v-if="evidence">
      <div class="col-12 col-md-6">
        <q-card>
          <q-card-section><div class="text-h6">Versiones</div></q-card-section>
          <q-list separator>
            <q-item v-for="version in evidence.versions" :key="version.id">
              <q-item-section avatar>
                <q-icon name="history" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Version {{ version.version_number }}</q-item-label>
                <q-item-label caption>{{ version.change_summary }}</q-item-label>
                <q-item-label caption>{{ version.file ? version.file.original_name : '' }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card>
          <q-card-section><div class="text-h6">Revisiones</div></q-card-section>
          <q-list separator>
            <q-item v-for="review in evidence.reviews" :key="review.id">
              <q-item-section avatar>
                <q-icon name="rate_review" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ review.action }}: {{ review.from_status || 'nuevo' }} -> {{ review.to_status }}</q-item-label>
                <q-item-label caption>{{ review.comment }}</q-item-label>
                <q-item-label caption>{{ review.reviewer ? review.reviewer.name : '' }} {{ formatDate(review.created_at) }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>

    <q-inner-loading :showing="loading" />

    <q-dialog v-model="reviewDialog">
      <q-card class="dialog-card">
        <q-card-section><div class="text-h6">Revision</div></q-card-section>
        <q-card-section>
          <q-input v-model="review.comment" label="Comentario" type="textarea" outlined />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" label="Guardar" :loading="saving" @click="submitReview" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="versionDialog">
      <q-card class="dialog-card">
        <q-card-section><div class="text-h6">Nueva version</div></q-card-section>
        <q-card-section>
          <q-input v-model="version.change_summary" label="Resumen de cambios" type="textarea" outlined class="q-mb-md" />
          <q-file v-model="version.file" label="Archivo" outlined clearable />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" label="Subir" :loading="saving" @click="submitVersion" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { getStoredUser } from 'src/utils/auth'

export default {
  name: 'EvidenceDetailPage',

  data () {
    return {
      loading: false,
      saving: false,
      evidence: null,
      reviewDialog: false,
      review: {
        action: null,
        comment: ''
      },
      versionDialog: false,
      version: {
        change_summary: '',
        file: null
      }
    }
  },

  created () {
    this.loadEvidence()
  },

  computed: {
    userPermissions () {
      const user = getStoredUser()
      return (user.permissions || []).map(item => item.name)
    }
  },

  methods: {
    async loadEvidence () {
      this.loading = true
      try {
        const response = await this.$api.get(`/evidences/${this.$route.params.id}`)
        this.evidence = response.data.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo cargar la evidencia' })
      } finally {
        this.loading = false
      }
    },

    openReviewDialog (action) {
      this.review = { action, comment: '' }
      this.reviewDialog = true
    },

    async submitReview () {
      this.saving = true
      try {
        await this.$api.post(`/evidences/${this.evidence.id}/${this.review.action}`, { comment: this.review.comment })
        this.$q.notify({ type: 'positive', message: 'Revision registrada' })
        this.reviewDialog = false
        this.loadEvidence()
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo registrar la revision' })
      } finally {
        this.saving = false
      }
    },

    openVersionDialog () {
      this.version = { change_summary: '', file: null }
      this.versionDialog = true
    },

    async submitVersion () {
      if (!this.version.file) {
        this.$q.notify({ type: 'warning', message: 'Selecciona un archivo' })
        return
      }

      this.saving = true
      try {
        const data = new FormData()
        data.append('change_summary', this.version.change_summary || '')
        data.append('file', this.version.file)
        await this.$api.post(`/evidences/${this.evidence.id}/versions`, data, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        this.$q.notify({ type: 'positive', message: 'Version registrada' })
        this.versionDialog = false
        this.loadEvidence()
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo subir la version' })
      } finally {
        this.saving = false
      }
    },

    statusColor (status) {
      const map = {
        uploaded: 'blue',
        in_review: 'indigo',
        observed: 'negative',
        corrected: 'orange',
        validated: 'positive',
        approved: 'green',
        ready_to_export: 'teal'
      }
      return map[status] || 'grey'
    },

    formatDate (value) {
      return value ? new Date(value).toLocaleString() : ''
    },

    can (permission) {
      return this.userPermissions.includes(permission)
    }
  }
}
</script>
