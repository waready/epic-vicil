<template>
  <q-page padding class="app-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Modelo de acreditacion</div>
        <div class="page-title">Criterios</div>
        <div class="page-subtitle">Consulta criterios, subcriterios y requerimientos del modelo seleccionado.</div>
      </div>
      <q-select v-model="modelId" :options="modelOptions" label="Modelo" outlined dense emit-value map-options class="model-select" @update:model-value="loadData" />
    </div>

    <q-list bordered separator class="criteria-list">
      <q-expansion-item
        v-for="criterion in criteria"
        :key="criterion.id"
        expand-separator
        :label="`${criterion.code} - ${criterion.name}`"
        icon="fact_check"
        @show="loadRequirementsForCriterion(criterion.id)"
      >
        <q-card flat>
          <q-card-section>
            <div class="text-subtitle2 q-mb-sm">Subcriterios</div>
            <q-chip v-for="subcriterion in criterion.subcriteria" :key="subcriterion.id" dense color="blue-grey-1" text-color="dark">
              {{ subcriterion.code }} {{ subcriterion.name }}
            </q-chip>
            <div class="text-subtitle2 q-mt-md q-mb-sm">Requerimientos</div>
            <q-inner-loading :showing="isLoadingRequirements(criterion.id)" />
            <q-list dense bordered>
              <q-item v-for="requirement in requirementsForCriterion(criterion.id)" :key="requirement.id">
                <q-item-section>
                  <q-item-label>{{ requirement.code }} - {{ requirement.name }}</q-item-label>
                  <q-item-label caption>{{ requirement.applies_to }} / {{ requirement.evidence_kind }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-if="!isLoadingRequirements(criterion.id) && requirementsForCriterion(criterion.id).length === 0">
                <q-item-section>
                  <q-item-label caption>Sin requerimientos registrados.</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </q-expansion-item>
    </q-list>

    <q-inner-loading :showing="loading" />
  </q-page>
</template>

<script>
export default {
  name: 'CriteriaPage',

  data () {
    return {
      loading: false,
      models: [],
      modelId: null,
      criteria: [],
      requirementsMap: {},
      loadingRequirementIds: []
    }
  },

  computed: {
    modelOptions () {
      return this.models.map(item => ({ label: item.name, value: item.id }))
    }
  },

  created () {
    this.loadModels()
  },

  methods: {
    async loadModels () {
      this.loading = true
      try {
        const response = await this.$api.get('/accreditation-models')
        this.models = response.data
        this.modelId = this.models.length ? this.models[0].id : null
        await this.loadData()
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los modelos' })
      } finally {
        this.loading = false
      }
    },

    async loadData () {
      if (!this.modelId) return
      this.loading = true
      try {
        const criteriaResponse = await this.$api.get('/accreditation-criteria', { params: { model_id: this.modelId } })
        this.criteria = criteriaResponse.data
        this.requirementsMap = {}
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los criterios' })
      } finally {
        this.loading = false
      }
    },

    async loadRequirementsForCriterion (criterionId) {
      if (this.requirementsMap[criterionId] || this.isLoadingRequirements(criterionId)) return

      this.loadingRequirementIds.push(criterionId)
      try {
        const response = await this.$api.get('/evidence-requirements', { params: { criterion_id: criterionId } })
        this.requirementsMap = {
          ...this.requirementsMap,
          [criterionId]: response.data
        }
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los requerimientos' })
      } finally {
        this.loadingRequirementIds = this.loadingRequirementIds.filter(id => id !== criterionId)
      }
    },

    requirementsForCriterion (criterionId) {
      return this.requirementsMap[criterionId] || []
    },

    isLoadingRequirements (criterionId) {
      return this.loadingRequirementIds.includes(criterionId)
    }
  }
}
</script>
