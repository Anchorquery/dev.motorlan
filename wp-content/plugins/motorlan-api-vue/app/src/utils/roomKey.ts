export function getPrePurchaseRoomKey(publicationId: number | string, viewerId: number | string): string {
  const pid = String(publicationId)
  const vid = String(viewerId)
  return `pub-${pid}-viewer-${vid}`
}

