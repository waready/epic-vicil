export const allowedEvidenceExtensions = [
  'pdf',
  'doc', 'docx', 'docm', 'dot', 'dotx', 'dotm', 'rtf', 'odt',
  'xls', 'xlsx', 'xlsm', 'xlsb', 'xlt', 'xltx', 'xltm', 'csv', 'ods',
  'ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'ppsm', 'pot', 'potx', 'potm', 'odp',
  'mpp', 'mpt', 'mpx',
  'vsd', 'vsdx', 'vsdm', 'vss', 'vssx', 'vssm', 'vst', 'vstx', 'vstm', 'vdx', 'vsx', 'vtx',
  'jpg', 'jpeg', 'png',
  'mp4', 'mov', 'm4v',
  'zip', 'rar', '7z'
]

export const acceptedEvidenceExtensions = allowedEvidenceExtensions
  .map(extension => `.${extension}`)
  .join(',')

export const allowedEvidenceExtensionsText = allowedEvidenceExtensions.join(', ')
