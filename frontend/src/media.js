// Client-side checks and compression for photos (Tuần 6, Tuần 7).
// Files are validated before upload to save bandwidth, then large photos are resized in the
// browser so the server never stores multi-megabyte phone pictures.

const MB = 1024 * 1024;

/**
 * Returns an error message, or '' when the file is acceptable.
 * Default rule (complaints): only .jpg / .png, at most 5 MB.
 */
export function validateFile(file, { types = ['image/jpeg', 'image/png'], extensions = ['jpg', 'jpeg', 'png'], maxMB = 5 } = {}) {
  if (!file) return 'Vui lòng chọn ảnh minh chứng.';

  const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
  if (!types.includes(file.type) || !extensions.includes(ext)) {
    return `Chỉ nhận file ${extensions.map((e) => '.' + e).join(', ')}.`;
  }
  if (file.size > maxMB * MB) {
    return `File quá lớn (${(file.size / MB).toFixed(1)} MB). Tối đa ${maxMB} MB.`;
  }
  return '';
}

/**
 * Resize a photo so its longest side is at most `maxSide` px and re-encode it as JPEG.
 * Videos, GIFs and small images are returned unchanged.
 */
export async function compressImage(file, { maxSide = 1600, quality = 0.82 } = {}) {
  if (!file || !file.type.startsWith('image/') || file.type === 'image/gif' || file.size < 300 * 1024) {
    return file;
  }

  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    if (!blob || blob.size >= file.size) return file;

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg' });
  } catch {
    return file;
  }
}

/** Replace the photo fields of a FormData with compressed versions. */
export async function compressFormImages(fd, keys = ['image', 'photo', 'media']) {
  for (const key of keys) {
    const value = fd.get(key);
    if (value instanceof File && value.size > 0) {
      fd.set(key, await compressImage(value));
    }
  }
  return fd;
}
