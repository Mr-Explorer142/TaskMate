export function openInNewTab(url: string): void {
  const popup = window.open(url, "_blank")

  if (!popup) {
    window.location.assign(url)
    return
  }

  popup.opener = null
}

export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement("a")
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}
