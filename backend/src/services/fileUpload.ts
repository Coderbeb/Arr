import fs from 'fs';
import path from 'path';
import crypto from 'crypto';

const UPLOAD_DIR = path.resolve(__dirname, '../../uploads');

// Allowed MIME types
const ALLOWED_IMAGES = ['image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_VIDEOS = ['video/mp4', 'video/webm', 'video/quicktime'];
const ALLOWED_DOCS = ['application/pdf'];
const ALL_ALLOWED = [...ALLOWED_IMAGES, ...ALLOWED_VIDEOS, ...ALLOWED_DOCS];

// Size limits
const MAX_IMAGE_SIZE = 10 * 1024 * 1024;   // 10MB
const MAX_VIDEO_SIZE = 50 * 1024 * 1024;   // 50MB
const MAX_DOC_SIZE = 15 * 1024 * 1024;     // 15MB

export interface UploadResult {
  url: string;
  filePath: string;
  hash: string;
  size: number;
  mimeType: string;
}

/**
 * Saves an uploaded file buffer to disk and returns metadata.
 */
export async function saveFile(
  buffer: Buffer,
  originalFilename: string,
  mimeType: string,
  category: 'trades' | 'disputes',
  entityId: string,
  fieldName: string,
): Promise<UploadResult> {
  // Validate MIME type
  if (!ALL_ALLOWED.includes(mimeType)) {
    throw new Error(`File type '${mimeType}' is not allowed. Accepted: images (jpg/png/webp), videos (mp4/webm), PDF.`);
  }

  // Validate file size
  const isImage = ALLOWED_IMAGES.includes(mimeType);
  const isVideo = ALLOWED_VIDEOS.includes(mimeType);
  const maxSize = isImage ? MAX_IMAGE_SIZE : isVideo ? MAX_VIDEO_SIZE : MAX_DOC_SIZE;

  if (buffer.length > maxSize) {
    const maxMB = Math.round(maxSize / (1024 * 1024));
    throw new Error(`File too large. Maximum ${maxMB}MB allowed for ${isImage ? 'images' : isVideo ? 'videos' : 'documents'}.`);
  }

  // Compute SHA-256 hash
  const hash = crypto.createHash('sha256').update(buffer).digest('hex');

  // Build directory and filename
  const ext = originalFilename.split('.').pop() || (isImage ? 'jpg' : isVideo ? 'mp4' : 'pdf');
  const safeFilename = `${fieldName}_${Date.now()}_${hash.slice(0, 8)}.${ext}`;
  const dir = path.join(UPLOAD_DIR, category, entityId);

  // Ensure directory exists
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }

  // Write file
  const filePath = path.join(dir, safeFilename);
  fs.writeFileSync(filePath, buffer);

  // URL path (served via @fastify/static)
  const url = `/uploads/${category}/${entityId}/${safeFilename}`;

  return { url, filePath, hash, size: buffer.length, mimeType };
}

/**
 * Check if a MIME type is a video format.
 */
export function isVideoMime(mime: string): boolean {
  return ALLOWED_VIDEOS.includes(mime);
}

/**
 * Check if a MIME type is an image format.
 */
export function isImageMime(mime: string): boolean {
  return ALLOWED_IMAGES.includes(mime);
}
