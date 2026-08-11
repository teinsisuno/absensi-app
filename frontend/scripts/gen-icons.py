"""Generate simple PWA icons (solid indigo + white circle) using only stdlib."""
import struct, zlib, os

def chunk(tag, data):
    c = struct.pack('>I', len(data)) + tag + data
    c += struct.pack('>I', zlib.crc32(tag + data) & 0xffffffff)
    return c

def make_png(size, bg=(79, 70, 229), fg=(255, 255, 255)):
    cx = cy = size / 2
    r_in = size * 0.28
    r_out = size * 0.40
    rows = []
    for y in range(size):
        row = bytearray([0])
        for x in range(size):
            dx, dy = x - cx, y - cy
            d = (dx * dx + dy * dy) ** 0.5
            if r_in <= d <= r_out:
                row += bytes(fg)
            else:
                row += bytes(bg)
        rows.append(bytes(row))
    raw = b''.join(rows)
    ihdr = struct.pack('>IIBBBBB', size, size, 8, 2, 0, 0, 0)  # 8-bit RGB
    return (b'\x89PNG\r\n\x1a\n'
            + chunk(b'IHDR', ihdr)
            + chunk(b'IDAT', zlib.compress(raw, 9))
            + chunk(b'IEND', b''))

out = os.path.join(os.path.dirname(__file__), '..', 'public', 'icons')
os.makedirs(out, exist_ok=True)
for s in (192, 512):
    path = os.path.join(out, f'icon-{s}.png')
    with open(path, 'wb') as f:
        f.write(make_png(s))
    print('wrote', os.path.abspath(path), os.path.getsize(path), 'bytes')
