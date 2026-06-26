const routes = [
  {
    path: '/login',
    component: () => import('pages/LoginPage.vue')
  },
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'change-password', component: () => import('pages/profile/ChangePasswordPage.vue') },
      { path: 'profile', component: () => import('pages/profile/ProfilePage.vue') },
      { path: 'dashboard', component: () => import('pages/DashboardPage.vue'), meta: { permission: 'view.dashboard' } },
      { path: 'evidences', component: () => import('pages/evidences/EvidenceListPage.vue'), meta: { permission: 'view.evidences', blockTeacherOnly: true } },
      { path: 'my-evidences', component: () => import('pages/evidences/MyEvidenceTasksPage.vue'), meta: { permission: 'create.evidences' } },
      { path: 'evidences/create', component: () => import('pages/evidences/EvidenceCreatePage.vue'), meta: { permission: 'create.evidences', blockTeacherOnly: true } },
      { path: 'evidences/:id', component: () => import('pages/evidences/EvidenceDetailPage.vue'), meta: { permission: 'view.evidences' } },
      { path: 'criteria', component: () => import('pages/catalogs/CriteriaPage.vue'), meta: { permission: 'manage.accreditation', blockTeacherOnly: true } },
      { path: 'users', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'accreditation-criteria', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'accreditation-subcriteria', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'evidence-requirements-admin', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'institutions', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'faculties', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'programs', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'study-plans', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'courses', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'course-offerings', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'teachers', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'admin/catalogs', component: () => import('pages/admin/AdminCatalogsPage.vue'), meta: { permission: 'manage.catalogs' } },
      { path: 'reports', component: () => import('pages/reports/ReportsPage.vue'), meta: { permission: 'view.dashboard', blockTeacherOnly: true } },
      { path: 'exports', component: () => import('pages/exports/ExportPage.vue'), meta: { permission: 'export.evidences' } }
    ]
  },
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue')
  }
]

export default routes
