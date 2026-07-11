export const SERVER_UPLOAD_LIMIT_MB = 15
export const BATCH_SERVER_UPLOAD_LIMIT_MB = 30

export async function uploadDirectFile ({ api, http, file, context, onProgress }) {
  const presign = await api.post('/uploads/direct/presign', {
    ...context,
    original_name: file.name,
    mime_type: file.type || 'application/octet-stream',
    size_bytes: file.size
  })

  await http.put(presign.data.upload_url, file, {
    headers: presign.data.headers || {},
    onUploadProgress: event => {
      if (event.total && onProgress) {
        onProgress(Math.round((event.loaded / event.total) * 100))
      }
    }
  })

  const completed = await api.post('/uploads/direct/complete', presign.data.file)
  if (onProgress) onProgress(100)

  return completed.data.data
}

export function shouldUseDirectUpload (file, serverLimitMb = SERVER_UPLOAD_LIMIT_MB) {
  const serverLimitBytes = serverLimitMb * 1024 * 1024

  return file.size >= serverLimitBytes || (file.type || '').startsWith('video/')
}

export function shouldUseDirectUploadForFiles (
  files,
  serverLimitMb = SERVER_UPLOAD_LIMIT_MB,
  batchServerLimitMb = BATCH_SERVER_UPLOAD_LIMIT_MB
) {
  const list = Array.from(files || [])
  const batchLimitBytes = batchServerLimitMb * 1024 * 1024
  const totalBytes = list.reduce((sum, file) => sum + file.size, 0)

  return totalBytes >= batchLimitBytes || list.some(file => shouldUseDirectUpload(file, serverLimitMb))
}

export function canFallbackToServer (files, error, serverLimitMb = SERVER_UPLOAD_LIMIT_MB) {
  const serverLimitBytes = serverLimitMb * 1024 * 1024
  const directUnavailable = !error.response ||
    ['ERR_NETWORK', 'ECONNABORTED'].includes(error.code) ||
    [400, 403, 404, 409, 422].includes(error.response?.status)

  return directUnavailable && files.every(file => file.size <= serverLimitBytes)
}
