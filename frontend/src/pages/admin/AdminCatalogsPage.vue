<template>
  <q-page padding class="app-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Administracion</div>
        <div class="page-title">{{ activeConfig.title }}</div>
        <div class="page-subtitle">Gestiona catalogos academicos y responsables desde una sola pantalla.</div>
      </div>
      <q-btn color="primary" icon="add" :label="`Nuevo ${activeConfig.singular}`" unelevated @click="openCreate" />
    </div>

    <q-card flat bordered class="data-panel">
      <q-table
        :rows="rows"
        :columns="activeConfig.columns"
        row-key="id"
        flat
        :loading="loading"
        :filter="filter"
        :pagination="pagination"
      >
        <template v-slot:top-right>
          <q-input v-model="filter" dense outlined debounce="250" placeholder="Buscar" clearable>
            <template v-slot:prepend>
              <q-icon name="search" />
            </template>
          </q-input>
        </template>

        <template v-slot:body-cell-is_active="props">
          <q-td :props="props">
            <q-chip dense :color="props.row.is_active ? 'positive' : 'grey-6'" text-color="white">
              {{ props.row.is_active ? 'Activo' : 'Inactivo' }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-chip dense :color="props.row.status === 'active' ? 'positive' : 'grey-6'" text-color="white">
              {{ props.row.status || 'Sin estado' }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-is_assessment_course="props">
          <q-td :props="props">
            <q-chip dense :color="props.row.is_assessment_course ? 'primary' : 'grey-5'" text-color="white">
              {{ props.row.is_assessment_course ? 'Assessment' : 'Regular' }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-requires_assessment_video="props">
          <q-td :props="props">
            <q-icon
              :name="props.row.requires_assessment_video ? 'videocam' : 'remove'"
              :color="props.row.requires_assessment_video ? 'negative' : 'grey-6'"
              size="20px"
            />
          </q-td>
        </template>

        <template v-slot:body-cell-roles="props">
          <q-td :props="props">
            <q-chip v-for="role in props.row.roles || []" :key="role.id || role.name" dense color="primary" text-color="white">
              {{ role.name }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props" class="q-gutter-xs">
            <q-btn v-if="activeTab === 'teachers' && !props.row.user" dense flat round icon="person_add" color="primary" @click="createTeacherAccount(props.row)">
              <q-tooltip>Crear cuenta de acceso docente</q-tooltip>
            </q-btn>
            <q-btn v-if="activeTab === 'teachers'" dense flat round icon="upload_file" color="secondary" @click="openCvDialog(props.row)">
              <q-tooltip>Subir CV como evidencia C6</q-tooltip>
            </q-btn>
            <q-btn dense flat round icon="edit" color="primary" @click="openEdit(props.row)">
              <q-tooltip>Editar</q-tooltip>
            </q-btn>
            <q-btn dense flat round icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>Eliminar</q-tooltip>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card class="admin-dialog">
        <q-form @submit.prevent="save">
          <q-card-section>
            <div class="text-h6">{{ editingId ? 'Editar' : 'Nuevo' }} {{ activeConfig.singular }}</div>
            <div class="text-body2 text-grey-7">{{ activeConfig.help }}</div>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <div class="row q-col-gutter-md">
              <div v-for="field in visibleFields" :key="field.name" :class="field.class || 'col-12 col-md-6'">
                <q-toggle
                  v-if="field.type === 'toggle'"
                  v-model="form[field.name]"
                  :label="field.label"
                  color="primary"
                />
                <q-select
                  v-else-if="field.type === 'select'"
                  v-model="form[field.name]"
                  :options="optionsFor(field.options)"
                  :label="field.label"
                  outlined
                  dense
                  emit-value
                  map-options
                  clearable
                  :multiple="field.multiple || false"
                  :use-chips="field.multiple || false"
                  :rules="field.required ? [val => field.multiple ? (val && val.length > 0 || 'Campo obligatorio') : (!!val || 'Campo obligatorio')] : []"
                />
                <q-input
                  v-else
                  v-model="form[field.name]"
                  :label="field.label"
                  :type="field.type || 'text'"
                  outlined
                  dense
                  :rules="field.required ? [val => !!val || 'Campo obligatorio'] : []"
                />
              </div>
            </div>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn color="primary" icon="save" label="Guardar" type="submit" unelevated :loading="saving" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="cvDialog" persistent>
      <q-card class="admin-dialog">
        <q-form @submit.prevent="submitTeacherCv">
          <q-card-section>
            <div class="text-h6">Subir CV docente</div>
            <div class="text-body2 text-grey-7">
              {{ selectedTeacher ? `${selectedTeacher.last_name}, ${selectedTeacher.first_name}` : '' }} - Criterio 6 / Cuerpo de Profesores.
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-select v-model="cvForm.program_id" :options="optionSets.programs" label="Programa" outlined dense emit-value map-options :rules="[val => !!val || 'Campo obligatorio']" />
              </div>
              <div class="col-12 col-md-6">
                <q-select v-model="cvForm.accreditation_cycle_id" :options="optionSets.cycles" label="Ciclo de acreditacion" outlined dense emit-value map-options :rules="[val => !!val || 'Campo obligatorio']" />
              </div>
              <div class="col-12">
                <q-input v-model="cvForm.title" label="Titulo" outlined dense />
              </div>
              <div class="col-12">
                <q-input v-model="cvForm.description" label="Descripcion" type="textarea" outlined />
              </div>
              <div class="col-12">
                <q-file v-model="cvForm.file" label="Archivo CV o soporte docente" outlined clearable counter :rules="[val => !!val || 'Selecciona un archivo']" />
              </div>
            </div>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn color="primary" icon="cloud_upload" label="Subir CV" type="submit" unelevated :loading="cvSaving" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  name: 'AdminCatalogsPage',

  data () {
    return {
      activeTab: 'faculties',
      filter: '',
      loading: false,
      saving: false,
      cvSaving: false,
      dialog: false,
      cvDialog: false,
      editingId: null,
      selectedTeacher: null,
      lastRowsRequestId: null,
      form: {},
      cvForm: {
        program_id: '',
        accreditation_cycle_id: '',
        title: '',
        description: '',
        file: null
      },
      rows: [],
      pagination: { rowsPerPage: 12 },
      optionSets: {
        institutions: [],
        faculties: [],
        programs: [],
        studyPlans: [],
        users: [],
        roles: [],
        cycles: [],
        terms: [],
        courses: [],
        teachers: [],
        offeringStatuses: [
          { label: 'Activo', value: 'active' },
          { label: 'Cerrado', value: 'closed' },
          { label: 'Suspendido', value: 'suspended' }
        ]
      },
      optionLoaded: {},
      catalogs: {
        users: {
          label: 'Usuarios',
          singular: 'usuario',
          title: 'Usuarios del sistema',
          icon: 'manage_accounts',
          endpoint: '/admin/users',
          help: 'Crea cuentas, asigna roles y define la contrasena inicial de acceso.',
          columns: [
            { name: 'name', label: 'Usuario', field: 'name', align: 'left', sortable: true },
            { name: 'email', label: 'Correo', field: 'email', align: 'left', sortable: true },
            { name: 'roles', label: 'Roles', field: 'roles', align: 'left' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'name', label: 'Nombre completo', required: true, class: 'col-12 col-md-6' },
            { name: 'email', label: 'Correo', type: 'email', required: true, class: 'col-12 col-md-6' },
            { name: 'password', label: 'Password inicial', type: 'password', required: true, createOnly: true },
            { name: 'password', label: 'Nuevo password', type: 'password', editOnly: true },
            { name: 'role_names', label: 'Roles', type: 'select', options: 'roles', multiple: true, required: true, class: 'col-12' },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        },
        institutions: {
          label: 'Instituciones',
          singular: 'institucion',
          title: 'Instituciones',
          icon: 'corporate_fare',
          endpoint: '/admin/institutions',
          help: 'Administra universidades o instituciones vinculadas.',
          columns: [
            { name: 'short_name', label: 'Sigla', field: 'short_name', align: 'left', sortable: true },
            { name: 'name', label: 'Institucion', field: 'name', align: 'left', sortable: true },
            { name: 'ruc', label: 'RUC', field: 'ruc', align: 'left' },
            { name: 'website', label: 'Web', field: 'website', align: 'left' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'short_name', label: 'Sigla', required: true },
            { name: 'name', label: 'Nombre', required: true, class: 'col-12' },
            { name: 'ruc', label: 'RUC' },
            { name: 'website', label: 'Sitio web' },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        },
        faculties: {
          label: 'Facultades',
          singular: 'facultad',
          title: 'Facultades',
          icon: 'account_balance',
          endpoint: '/admin/faculties',
          help: 'Administra facultades y unidades academicas.',
          columns: [
            { name: 'code', label: 'Codigo', field: 'code', align: 'left', sortable: true },
            { name: 'name', label: 'Facultad', field: 'name', align: 'left', sortable: true },
            { name: 'institution', label: 'Institucion', field: row => row.institution ? row.institution.name : '', align: 'left' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'institution_id', label: 'Institucion', type: 'select', options: 'institutions', required: true },
            { name: 'code', label: 'Codigo', required: true },
            { name: 'name', label: 'Nombre', required: true, class: 'col-12' },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        },
        programs: {
          label: 'Programas',
          singular: 'programa',
          title: 'Programas academicos',
          icon: 'school',
          endpoint: '/admin/programs',
          help: 'Administra escuelas profesionales, grados y modalidad.',
          columns: [
            { name: 'code', label: 'Codigo', field: 'code', align: 'left', sortable: true },
            { name: 'name', label: 'Programa', field: 'name', align: 'left', sortable: true },
            { name: 'faculty', label: 'Facultad', field: row => row.faculty ? row.faculty.name : '', align: 'left' },
            { name: 'modality', label: 'Modalidad', field: 'modality', align: 'left' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'faculty_id', label: 'Facultad', type: 'select', options: 'faculties', required: true },
            { name: 'code', label: 'Codigo', required: true },
            { name: 'name', label: 'Nombre', required: true, class: 'col-12' },
            { name: 'degree_name', label: 'Grado academico' },
            { name: 'professional_title', label: 'Titulo profesional' },
            { name: 'modality', label: 'Modalidad' },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        },
        studyPlans: {
          label: 'Planes',
          singular: 'plan',
          title: 'Planes de estudio',
          icon: 'menu_book',
          endpoint: '/admin/study-plans',
          help: 'Administra planes curriculares por programa.',
          columns: [
            { name: 'code', label: 'Codigo', field: 'code', align: 'left', sortable: true },
            { name: 'name', label: 'Plan', field: 'name', align: 'left', sortable: true },
            { name: 'program', label: 'Programa', field: row => row.program ? row.program.name : '', align: 'left' },
            { name: 'year', label: 'Anio', field: 'year', align: 'center' },
            { name: 'is_current', label: 'Vigente', field: row => row.is_current ? 'Si' : 'No', align: 'center' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'program_id', label: 'Programa', type: 'select', options: 'programs', required: true },
            { name: 'code', label: 'Codigo', required: true },
            { name: 'name', label: 'Nombre', required: true, class: 'col-12' },
            { name: 'year', label: 'Anio', type: 'number' },
            { name: 'approved_on', label: 'Fecha de aprobacion', type: 'date' },
            { name: 'approval_document', label: 'Documento de aprobacion', class: 'col-12' },
            { name: 'is_current', label: 'Plan vigente', type: 'toggle', default: false },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        },
        courses: {
          label: 'Cursos',
          singular: 'curso',
          title: 'Cursos',
          icon: 'class',
          endpoint: '/admin/courses',
          help: 'Administra cursos del plan de estudios.',
          columns: [
            { name: 'code', label: 'Codigo', field: 'code', align: 'left', sortable: true },
            { name: 'name', label: 'Curso', field: 'name', align: 'left', sortable: true },
            { name: 'plan', label: 'Plan', field: row => row.study_plan ? row.study_plan.code : '', align: 'left' },
            { name: 'cycle_number', label: 'Ciclo', field: 'cycle_number', align: 'center' },
            { name: 'credits', label: 'Creditos', field: 'credits', align: 'center' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'study_plan_id', label: 'Plan de estudios', type: 'select', options: 'studyPlans', required: true },
            { name: 'code', label: 'Codigo', required: true },
            { name: 'name', label: 'Nombre', required: true, class: 'col-12' },
            { name: 'cycle_number', label: 'Ciclo', type: 'number' },
            { name: 'credits', label: 'Creditos', type: 'number' },
            { name: 'theory_hours', label: 'Horas teoria', type: 'number' },
            { name: 'practice_hours', label: 'Horas practica', type: 'number' },
            { name: 'lab_hours', label: 'Horas laboratorio', type: 'number' },
            { name: 'is_required', label: 'Obligatorio', type: 'toggle', default: true },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        },
        courseOfferings: {
          label: 'Carga docente',
          singular: 'carga docente',
          title: 'Carga docente por curso',
          icon: 'event_note',
          endpoint: '/admin/course-offerings',
          help: 'Asigna cursos, semestre academico y docente responsable para generar evidencias C5 por curso.',
          columns: [
            { name: 'course', label: 'Curso', field: row => row.course ? `${row.course.code} - ${row.course.name}` : '', align: 'left', sortable: true },
            { name: 'program', label: 'Programa', field: row => row.program ? row.program.code : '', align: 'left', sortable: true },
            { name: 'term', label: 'Semestre', field: row => row.term ? row.term.code : '', align: 'left', sortable: true },
            { name: 'section', label: 'Seccion', field: 'section', align: 'center' },
            { name: 'teacher', label: 'Docente principal', field: row => {
              const assignment = (row.assignments || []).find(item => item.role === 'main')
              const teacher = assignment ? assignment.teacher : null
              return teacher ? `${teacher.last_name}, ${teacher.first_name}` : ''
            }, align: 'left' },
            { name: 'assessment_result_code', label: 'RE', field: 'assessment_result_code', align: 'center', sortable: true },
            { name: 'is_assessment_course', label: 'Medicion', field: 'is_assessment_course', align: 'center' },
            { name: 'requires_assessment_video', label: 'Video', field: 'requires_assessment_video', align: 'center' },
            { name: 'enrolled_count', label: 'Matriculados', field: 'enrolled_count', align: 'center' },
            { name: 'status', label: 'Estado', field: 'status', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'program_id', label: 'Programa', type: 'select', options: 'programs', required: true },
            { name: 'academic_term_id', label: 'Semestre', type: 'select', options: 'terms', required: true },
            { name: 'study_plan_id', label: 'Curricula / plan', type: 'select', options: 'studyPlans', clientOnly: true, class: 'col-12 col-md-6' },
            { name: 'course_id', label: 'Curso', type: 'select', options: 'courses', required: true, class: 'col-12 col-md-6' },
            { name: 'teacher_id', label: 'Docente principal', type: 'select', options: 'teachers' },
            { name: 'weekly_hours', label: 'Horas semanales', type: 'number' },
            { name: 'section', label: 'Seccion' },
            { name: 'group_code', label: 'Codigo de grupo' },
            { name: 'enrolled_count', label: 'Matriculados', type: 'number' },
            { name: 'is_assessment_course', label: 'Curso de medicion / assessment', type: 'toggle', default: false },
            { name: 'assessment_result_code', label: 'Resultado del estudiante (RE-Ixx)', visibleWhen: form => form.is_assessment_course },
            { name: 'assessment_result_name', label: 'Nombre del resultado', visibleWhen: form => form.is_assessment_course, class: 'col-12 col-md-6' },
            { name: 'requires_assessment_video', label: 'Requiere video de 10 minutos', type: 'toggle', default: false, visibleWhen: form => form.is_assessment_course },
            { name: 'status', label: 'Estado', type: 'select', options: 'offeringStatuses', default: 'active' }
          ]
        },
        teachers: {
          label: 'Docentes',
          singular: 'docente',
          title: 'Docentes',
          icon: 'groups',
          endpoint: '/admin/teachers',
          help: 'Administra docentes, especialidad y usuario asociado.',
          columns: [
            { name: 'name', label: 'Docente', field: row => `${row.last_name}, ${row.first_name}`, align: 'left', sortable: true },
            { name: 'email', label: 'Correo', field: 'email', align: 'left' },
            { name: 'degree', label: 'Grado', field: 'highest_degree', align: 'left' },
            { name: 'specialty', label: 'Especialidad', field: 'specialty', align: 'left' },
            { name: 'user', label: 'Usuario', field: row => row.user ? row.user.email : '', align: 'left' },
            { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
            { name: 'actions', label: 'Acciones', align: 'center' }
          ],
          fields: [
            { name: 'institution_id', label: 'Institucion', type: 'select', options: 'institutions', required: true },
            { name: 'user_id', label: 'Usuario existente', type: 'select', options: 'users' },
            { name: 'document_type', label: 'Tipo documento' },
            { name: 'document_number', label: 'Numero documento' },
            { name: 'first_name', label: 'Nombres', required: true },
            { name: 'last_name', label: 'Apellidos', required: true },
            { name: 'email', label: 'Correo', type: 'email' },
            { name: 'phone', label: 'Telefono' },
            { name: 'highest_degree', label: 'Grado academico' },
            { name: 'specialty', label: 'Especialidad' },
            { name: 'employment_type', label: 'Tipo de vinculacion' },
            { name: 'create_user', label: 'Crear usuario docente', type: 'toggle', default: false, createOnly: true },
            { name: 'password', label: 'Password inicial', type: 'password', visibleWhen: form => form.create_user, createOnly: true },
            { name: 'is_active', label: 'Activo', type: 'toggle', default: true }
          ]
        }
      }
    }
  },

  computed: {
    catalogList () {
      return Object.keys(this.catalogs).map(name => ({ name, ...this.catalogs[name] }))
    },

    activeConfig () {
      return this.catalogs[this.activeTab]
    },

    visibleFields () {
      return this.activeConfig.fields.filter(field => {
        if (field.createOnly && this.editingId) return false
        if (field.editOnly && !this.editingId) return false
        if (field.visibleWhen) return field.visibleWhen(this.form)
        return true
      })
    }
  },

  watch: {
    async activeTab () {
      this.filter = ''
      this.rows = []
      await this.loadOptionsForActive()
      this.loadRows()
    },

    'form.study_plan_id' () {
      if (this.activeTab !== 'courseOfferings') return
      const options = this.optionsFor('courses')
      if (!options.some(item => item.value === this.form.course_id)) {
        this.form.course_id = options.length ? options[0].value : ''
      }
    },

    '$route.path' () {
      this.syncTabFromRoute()
    }
  },

  created () {
    this.syncTabFromRoute()
    this.loadOptionsForActive()
    this.loadRows()
  },

  methods: {
    syncTabFromRoute () {
      const map = {
        '/users': 'users',
        '/institutions': 'institutions',
        '/faculties': 'faculties',
        '/programs': 'programs',
        '/study-plans': 'studyPlans',
        '/courses': 'courses',
        '/course-offerings': 'courseOfferings',
        '/teachers': 'teachers'
      }
      this.activeTab = map[this.$route.path] || this.activeTab
    },

    async loadOptionsForActive () {
      await this.ensureOptionSets(this.requiredOptionSets())
    },

    requiredOptionSets () {
      const sets = new Set()
      ;(this.activeConfig.fields || []).forEach(field => {
        if (field.options) sets.add(field.options)
      })
      return Array.from(sets)
    },

    async ensureOptionSets (names) {
      const pending = names.filter(name => name && !this.optionLoaded[name])
      if (!pending.length) return

      await Promise.all(pending.map(name => this.loadOptionSet(name)))
    },

    async loadOptionSet (name) {
      try {
        if (name === 'institutions') {
          const response = await this.$api.get('/admin/institutions')
          this.optionSets.institutions = response.data.map(item => ({ label: item.name, value: item.id }))
        }

        if (name === 'faculties') {
          const response = await this.$api.get('/admin/faculties')
          this.optionSets.faculties = response.data.map(item => ({ label: `${item.code || ''} ${item.name}`.trim(), value: item.id }))
        }

        if (name === 'programs') {
          const response = await this.$api.get('/admin/programs')
          this.optionSets.programs = response.data.map(item => ({ label: `${item.code} - ${item.name}`, value: item.id }))
        }

        if (name === 'studyPlans') {
          const response = await this.$api.get('/admin/study-plans')
          this.optionSets.studyPlans = response.data.map(item => ({ label: `${item.code || 'Plan'} - ${item.name}`, value: item.id }))
        }

        if (name === 'users') {
          const response = await this.$api.get('/admin/users', { params: { limit: 200 } })
          this.optionSets.users = response.data.map(item => ({ label: `${item.name} - ${item.email}`, value: item.id }))
        }

        if (name === 'roles') {
          const response = await this.$api.get('/admin/roles')
          this.optionSets.roles = response.data.map(item => ({ label: item.name.replaceAll('_', ' '), value: item.name }))
        }

        if (name === 'cycles') {
          const response = await this.$api.get('/accreditation-cycles')
          this.optionSets.cycles = response.data.map(item => ({
            label: `${item.name} - ${item.program ? item.program.code : ''}`.trim(),
            value: item.id
          }))
        }

        if (name === 'terms') {
          const response = await this.$api.get('/semesters')
          this.optionSets.terms = response.data.map(item => ({
            label: `${item.code || item.name} ${item.year ? '- ' + item.year.year : ''}`.trim(),
            value: item.id
          }))
        }

        if (name === 'courses') {
          const response = await this.$api.get('/admin/courses')
          this.optionSets.courses = response.data.map(item => ({
            label: `${item.code} - ${item.name}`,
            value: item.id,
            study_plan_id: item.study_plan_id,
            program_id: item.study_plan && item.study_plan.program ? item.study_plan.program.id : null
          }))
        }

        if (name === 'teachers') {
          const response = await this.$api.get('/admin/teachers')
          this.optionSets.teachers = response.data.map(item => ({
            label: `${item.last_name}, ${item.first_name}${item.email ? ' - ' + item.email : ''}`,
            value: item.id
          }))
        }

        this.optionLoaded[name] = true
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar opciones administrativas' })
      }
    },

    async loadRows () {
      const requestId = Date.now()
      this.lastRowsRequestId = requestId
      this.loading = true
      this.rows = []
      try {
        const response = await this.$api.get(this.activeConfig.endpoint)
        if (this.lastRowsRequestId !== requestId) return
        this.rows = response.data
      } catch (error) {
        if (this.lastRowsRequestId !== requestId) return
        this.$q.notify({ type: 'negative', message: `No se pudo cargar ${this.activeConfig.title.toLowerCase()}` })
      } finally {
        if (this.lastRowsRequestId === requestId) {
          this.loading = false
        }
      }
    },

    optionsFor (name) {
      if (name === 'courses' && this.activeTab === 'courseOfferings') {
        return (this.optionSets.courses || []).filter(item => {
          if (this.form.study_plan_id) return item.study_plan_id === this.form.study_plan_id
          if (this.form.program_id) return item.program_id === this.form.program_id
          return true
        })
      }
      return this.optionSets[name] || []
    },

    mainAssignment (row) {
      return (row.assignments || []).find(item => item.role === 'main') || null
    },

    openCreate () {
      this.editingId = null
      this.form = this.emptyForm()
      this.dialog = true
    },

    openEdit (row) {
      this.editingId = row.id
      const form = this.emptyForm()
      this.activeConfig.fields.forEach(field => {
        if (Object.prototype.hasOwnProperty.call(row, field.name)) {
          form[field.name] = row[field.name]
        }
      })
      if (this.activeTab === 'users') {
        form.role_names = (row.roles || []).map(role => role.name)
        form.password = ''
      }
      if (form.approved_on && typeof form.approved_on === 'string') {
        form.approved_on = form.approved_on.slice(0, 10)
      }
      if (this.activeTab === 'courseOfferings') {
        const assignment = this.mainAssignment(row)
        form.teacher_id = assignment ? assignment.teacher_id : ''
        form.weekly_hours = assignment ? assignment.weekly_hours : ''
        form.study_plan_id = row.course ? row.course.study_plan_id : ''
      }
      this.form = form
      this.dialog = true
    },

    emptyForm () {
      const form = {}
      this.activeConfig.fields.forEach(field => {
        if (field.multiple) {
          form[field.name] = []
        } else if (field.type === 'toggle') {
          form[field.name] = field.default !== undefined ? field.default : false
        } else {
          form[field.name] = ''
        }
      })

      const firstValue = key => this.optionSets[key] && this.optionSets[key].length ? this.optionSets[key][0].value : ''
      if (this.activeTab === 'faculties') form.institution_id = firstValue('institutions')
      if (this.activeTab === 'programs') form.faculty_id = firstValue('faculties')
      if (this.activeTab === 'studyPlans') form.program_id = firstValue('programs')
      if (this.activeTab === 'courses') form.study_plan_id = firstValue('studyPlans')
      if (this.activeTab === 'teachers') form.institution_id = firstValue('institutions')
      if (this.activeTab === 'users') {
        form.role_names = this.optionSets.roles.some(item => item.value === 'consulta') ? ['consulta'] : []
      }
      if (this.activeTab === 'courseOfferings') {
        form.program_id = firstValue('programs')
        form.academic_term_id = firstValue('terms')
        form.study_plan_id = firstValue('studyPlans')
        form.course_id = this.optionsFor('courses').length ? this.optionsFor('courses')[0].value : firstValue('courses')
        form.teacher_id = firstValue('teachers')
        form.status = 'active'
      }

      return form
    },

    payload () {
      const data = {}
      this.activeConfig.fields.forEach(field => {
        if (field.createOnly && this.editingId) return
        if (field.editOnly && !this.editingId) return
        if (field.clientOnly) return
        const value = this.form[field.name]
        if (value === '') {
          data[field.name] = null
        } else {
          data[field.name] = value
        }
      })

      if (!data.password) delete data.password
      if (this.activeTab === 'courseOfferings' && !data.is_assessment_course) {
        data.assessment_result_code = null
        data.assessment_result_name = null
        data.requires_assessment_video = false
      }
      return data
    },

    async save () {
      this.saving = true
      try {
        if (this.editingId) {
          await this.$api.put(`${this.activeConfig.endpoint}/${this.editingId}`, this.payload())
        } else {
          await this.$api.post(this.activeConfig.endpoint, this.payload())
        }

        this.$q.notify({ type: 'positive', message: 'Registro guardado' })
        this.dialog = false
        this.optionLoaded = {}
        await this.loadOptionsForActive()
        await this.loadRows()
      } catch (error) {
        const message = error.response && error.response.data && error.response.data.message
          ? error.response.data.message
          : 'No se pudo guardar el registro'
        this.$q.notify({ type: 'negative', message })
      } finally {
        this.saving = false
      }
    },

    confirmDelete (row) {
      this.$q.dialog({
        title: 'Eliminar registro',
        message: 'Esta accion quitara el registro del catalogo activo.',
        cancel: true,
        persistent: true
      }).onOk(() => this.remove(row))
    },

    async openCvDialog (teacher) {
      await this.ensureOptionSets(['programs', 'cycles'])
      this.selectedTeacher = teacher
      this.cvForm = {
        program_id: this.optionSets.programs.length ? this.optionSets.programs[0].value : '',
        accreditation_cycle_id: this.optionSets.cycles.length ? this.optionSets.cycles[0].value : '',
        title: `CV docente - ${teacher.last_name}, ${teacher.first_name}`,
        description: 'Curriculum vitae y documentos de soporte docente.',
        file: null
      }
      this.cvDialog = true
    },

    async submitTeacherCv () {
      if (!this.selectedTeacher || !this.cvForm.file) {
        this.$q.notify({ type: 'warning', message: 'Selecciona el archivo CV' })
        return
      }

      this.cvSaving = true
      try {
        const data = new FormData()
        data.append('program_id', this.cvForm.program_id)
        data.append('accreditation_cycle_id', this.cvForm.accreditation_cycle_id)
        data.append('title', this.cvForm.title || '')
        data.append('description', this.cvForm.description || '')
        data.append('file', this.cvForm.file)

        await this.$api.post(`/admin/teachers/${this.selectedTeacher.id}/cv`, data, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        this.$q.notify({ type: 'positive', message: 'CV registrado como evidencia C6' })
        this.cvDialog = false
      } catch (error) {
        const message = error.response && error.response.data && error.response.data.message
          ? error.response.data.message
          : 'No se pudo subir el CV'
        this.$q.notify({ type: 'negative', message })
      } finally {
        this.cvSaving = false
      }
    },

    createTeacherAccount (teacher) {
      if (!teacher.email) {
        this.$q.notify({ type: 'warning', message: 'El docente necesita correo para crear cuenta' })
        return
      }

      this.$q.dialog({
        title: 'Crear cuenta docente',
        message: `Se creara acceso para ${teacher.last_name}, ${teacher.first_name}. Si dejas el campo vacio se usara password.`,
        prompt: {
          model: '',
          type: 'password',
          label: 'Password inicial'
        },
        cancel: true,
        persistent: true
      }).onOk(async password => {
        try {
          await this.$api.post(`/admin/teachers/${teacher.id}/user`, {
            email: teacher.email,
            password: password || 'password'
          })
          this.$q.notify({ type: 'positive', message: 'Cuenta docente creada' })
          this.optionLoaded.users = false
          this.optionLoaded.teachers = false
          await this.loadOptionsForActive()
          await this.loadRows()
        } catch (error) {
          const message = error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'No se pudo crear la cuenta docente'
          this.$q.notify({ type: 'negative', message })
        }
      })
    },

    async remove (row) {
      try {
        await this.$api.delete(`${this.activeConfig.endpoint}/${row.id}`)
        this.$q.notify({ type: 'positive', message: 'Registro eliminado' })
        this.optionLoaded = {}
        await this.loadOptionsForActive()
        await this.loadRows()
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo eliminar el registro' })
      }
    }
  }
}
</script>
