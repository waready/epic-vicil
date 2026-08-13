import { configure } from 'quasar/wrappers'

export default configure(function () {
  const apiUrl = process.env.API_URL || (
    process.env.NODE_ENV === 'production'
      ? 'https://waready.org.pe/api'
      : 'http://localhost:8000/api'
  )

  return {
    boot: ['axios'],
    css: ['app.scss'],
    extras: ['material-icons'],
    build: {
      vueRouterMode: 'history',
      env: {
        API_URL: apiUrl
      }
    },
    devServer: {
      open: false,
      port: 9000
    },
    framework: {
      config: {
        brand: {
          primary: '#145c8f',
          secondary: '#991b1f',
          accent: '#c99a2e',
          positive: '#1f7a4d',
          negative: '#b42318',
          info: '#1c75af',
          warning: '#c99a2e',
          dark: '#172234'
        }
      },
      plugins: ['Notify', 'Dialog', 'Loading']
    }
  }
})
