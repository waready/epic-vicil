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
      <q-separator v-if="evidence.current_file" />
      <q-card-section v-if="evidence.current_file">
        <div class="file-viewer">
          <div class="file-viewer__header">
            <div class="file-viewer__identity">
              <q-avatar color="blue-1" text-color="primary" :icon="fileIcon(evidence.current_file)" />
              <div>
                <div class="file-viewer__title">{{ evidence.current_file.original_name }}</div>
                <div class="file-viewer__meta">
                  {{ evidence.current_file.extension?.toUpperCase() || 'ARCHIVO' }}
                  <span v-if="evidence.current_file.size_bytes"> &middot; {{ formatSize(evidence.current_file.size_bytes) }}</span>
                  <span v-if="evidence.current_file.temporary_url_expires_in_minutes">
                    &middot; enlace temporal {{ evidence.current_file.temporary_url_expires_in_minutes }} min
                  </span>
                </div>
              </div>
            </div>
            <div class="file-viewer__actions">
              <q-btn
                outline
                no-caps
                color="primary"
                icon="open_in_new"
                label="Abrir"
                :href="evidence.current_file.preview_url"
                target="_blank"
                :disable="!evidence.current_file.preview_url"
              />
              <q-btn
                unelevated
                no-caps
                color="primary"
                icon="download"
                label="Descargar"
                :href="evidence.current_file.download_url || evidence.current_file.preview_url"
                target="_blank"
                :disable="!evidence.current_file.download_url && !evidence.current_file.preview_url"
              />
            </div>
          </div>

          <div v-if="evidence.current_file.can_preview && evidence.current_file.preview_url" class="file-preview">
            <iframe
              v-if="evidence.current_file.file_type === 'pdf'"
              class="file-preview__frame"
              :src="evidence.current_file.preview_url"
              title="Vista previa PDF"
            />
            <q-img
              v-else-if="evidence.current_file.file_type === 'image'"
              class="file-preview__image"
              :src="evidence.current_file.preview_url"
              fit="contain"
            />
            <video
              v-else-if="evidence.current_file.file_type === 'video'"
              class="file-preview__video"
              :src="evidence.current_file.preview_url"
              controls
              preload="metadata"
            />
          </div>
          <q-banner v-else class="file-preview__empty" rounded>
            <template #avatar>
              <q-icon :name="fileIcon(evidence.current_file)" color="primary" />
            </template>
            Este tipo de archivo no tiene vista previa integrada. Puedes abrirlo o descargarlo.
          </q-banner>
        </div>
      </q-card-section>
    </q-card>

    <div class="row q-col-gutter-md" v-if="evidence">
      <div class="col-12 col-md-6">
        <q-card>
          <q-card-section><div class="text-h6">Versiones</div></q-card-section>
          <q-list separator>
            <q-item v-for="version in evidence.versions" :key="version.id">
              <q-item-section avatar>
                <q-icon :name="fileIcon(version.file)" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Version {{ version.version_number }}</q-item-label>
                <q-item-label caption>{{ version.change_summary }}</q-item-label>
                <q-item-label caption>{{ version.file ? version.file.original_name : '' }}</q-item-label>
              </q-item-section>
              <q-item-section side v-if="version.file">
                <div class="row no-wrap q-gutter-xs">
                  <q-btn
                    flat
                    dense
                    round
                    icon="open_in_new"
                    :href="version.file.preview_url"
                    target="_blank"
                    :disable="!version.file.preview_url"
                  >
                    <q-tooltip>Abrir version</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    dense
                    round
                    icon="download"
                    :href="version.file.download_url || version.file.preview_url"
                    target="_blank"
                    :disable="!version.file.download_url && !version.file.preview_url"
                  >
                    <q-tooltip>Descargar version</q-tooltip>
                  </q-btn>
                </div>
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
          <q-file v-model="version.file" label="Archivo" outlined clearable :disable="saving" />
          <div v-if="directUploading" class="q-mt-md">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-grey-7">Subiendo directamente al almacenamiento externo</span>
              <span class="text-caption text-weight-bold">{{ uploadProgress }}%</span>
            </div>
            <q-linear-progress :value="uploadProgress / 100" color="primary" rounded />
          </div>
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
import { canFallbackToServer, uploadDirectFile } from 'src/utils/directUpload'

export default {
  name: 'EvidenceDetailPage',

  data () {
    return {
      loading: false,
      saving: false,
      directUploading: false,
      uploadProgress: 0,
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
      this.uploadProgress = 0
      this.versionDialog = true
    },

    async submitVersion () {
      if (!this.version.file) {
        this.$q.notify({ type: 'warning', message: 'Selecciona un archivo' })
        return
      }

      this.saving = true
      this.uploadProgress = 0
      try {
        let asset = null

        try {
          asset = await this.uploadVersionDirect(this.version.file)
        } catch (directError) {
          if (!canFallbackToServer([this.version.file], directError)) {
            throw directError
          }

          await this.submitVersionThroughServer()
        }

        if (asset) {
          await this.$api.post(`/evidences/${this.evidence.id}/versions`, {
            change_summary: this.version.change_summary || '',
            file_asset_id: asset.id
          })
        }

        this.$q.notify({ type: 'positive', message: 'Version registrada' })
        this.versionDialog = false
        this.loadEvidence()
      } catch (error) {
        const message = error.response?.data?.message || 'No se pudo subir la version'
        this.$q.notify({ type: 'negative', message })
      } finally {
        this.saving = false
        this.directUploading = false
      }
    },

    async uploadVersionDirect (file) {
      this.directUploading = true

      const context = this.evidence.evidence_task_id
        ? { evidence_task_id: this.evidence.evidence_task_id }
        : {
            program_id: this.evidence.program_id,
            accreditation_cycle_id: this.evidence.accreditation_cycle_id,
            criterion_id: this.evidence.criterion_id
          }

      return uploadDirectFile({
        api: this.$api,
        http: this.$axios,
        file,
        context,
        onProgress: progress => {
          this.uploadProgress = progress
        }
      })
    },

    submitVersionThroughServer () {
      this.directUploading = false
      const data = new FormData()
      data.append('change_summary', this.version.change_summary || '')
      data.append('file', this.version.file)

      return this.$api.post(`/evidences/${this.evidence.id}/versions`, data, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
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

    formatSize (bytes) {
      if (!bytes) return ''
      const units = ['B', 'KB', 'MB', 'GB']
      let size = Number(bytes)
      let unit = 0

      while (size >= 1024 && unit < units.length - 1) {
        size /= 1024
        unit += 1
      }

      return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
    },

    fileIcon (file) {
      const type = file?.file_type
      const map = {
        pdf: 'picture_as_pdf',
        image: 'image',
        video: 'movie',
        document: 'article',
        spreadsheet: 'table_chart',
        presentation: 'slideshow',
        archive: 'folder_zip'
      }

      return map[type] || 'description'
    },

    can (permission) {
      return this.userPermissions.includes(permission)
    }
  }
}
</script>

<style scoped>
.file-viewer {
  display: grid;
  gap: 16px;
}

.file-viewer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.file-viewer__identity {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 12px;
}

.file-viewer__title {
  color: #172234;
  font-weight: 800;
  overflow-wrap: anywhere;
}

.file-viewer__meta {
  color: #667085;
  font-size: 0.86rem;
}

.file-viewer__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.file-preview {
  overflow: hidden;
  border: 1px solid #dbe4ee;
  border-radius: 10px;
  background: #f8fafc;
}

.file-preview__frame {
  display: block;
  width: 100%;
  min-height: min(72vh, 720px);
  border: 0;
}

.file-preview__image {
  width: 100%;
  max-height: 720px;
}

.file-preview__video {
  display: block;
  width: 100%;
  max-height: 720px;
  background: #000;
}

.file-preview__empty {
  border: 1px solid #dbe4ee;
  background: #f8fafc;
  color: #475467;
}

.dialog-card {
  width: min(560px, calc(100vw - 32px));
}

@media (max-width: 720px) {
  .file-viewer__header {
    align-items: stretch;
    flex-direction: column;
  }

  .file-viewer__actions {
    justify-content: flex-start;
  }
}
</style>
