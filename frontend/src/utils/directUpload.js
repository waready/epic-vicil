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

export function canFallbackToServer (files, error, serverLimitMb = 500) {
  const serverLimitBytes = serverLimitMb * 1024 * 1024
  const directUnavailable = !error.response ||
    ['ERR_NETWORK', 'ECONNABORTED'].includes(error.code) ||
    [400, 403, 404, 409, 422].includes(error.response?.status)

  return directUnavailable && files.every(file => file.size <= serverLimitBytes)
}
