# Draws the Forum801 logo (public/img/logo.png) in the spirit of the original logo:
# rounded bold letters with a blue gradient, a thick dark outline, a highlight and a piece of cheese on top.
# Font: Fredoka (SIL Open Font License, see OFL.txt). Run: python3 resources/logo/generate.py
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, '..', '..', 'public', 'img', 'logo.png')
TEXT = 'Forum801'
SCALE = 2  # drawn at 2x for sharp rendering on high density screens
W, H = 240 * SCALE, 84 * SCALE
INK = (29, 36, 48, 255)

font = ImageFont.truetype(os.path.join(HERE, 'Fredoka.ttf'), 46 * SCALE)
font.set_variation_by_name('Bold')

img = Image.new('RGBA', (W, H), (0, 0, 0, 0))
x, y = 8 * SCALE, 24 * SCALE
stroke = 4 * SCALE

# A big wedge of cheese behind the top of the word, like the original logo
def draw_cheese(target):
    layer = Image.new('RGBA', (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    s = SCALE
    P = lambda pts: [(px * s, py * s) for px, py in pts]
    top_face = P([(66, 30), (120, 3), (158, 15), (108, 38)])
    front_face = P([(108, 38), (158, 15), (158, 33), (110, 56)])
    side_face = P([(66, 30), (108, 38), (110, 56), (70, 45)])
    d.polygon(side_face, fill=(214, 150, 40, 255))
    d.polygon(front_face, fill=(242, 190, 72, 255))
    d.polygon(top_face, fill=(255, 222, 116, 255))
    # Holes seen from above, and cut holes on the sides
    for cx, cy, rx, ry in ((120, 16, 10, 4.5), (98, 27, 6, 2.8), (140, 15, 5, 2.4), (83, 31, 3.5, 1.6)):
        d.ellipse(((cx - rx) * s, (cy - ry) * s, (cx + rx) * s, (cy + ry) * s), fill=(205, 140, 30, 255), outline=(150, 95, 18, 255), width=s)
    for cx, cy, r in ((128, 40, 4.5), (147, 30, 3), (88, 43, 3.2)):
        d.ellipse(((cx - r) * s, (cy - r) * s, (cx + r) * s, (cy + r) * s), fill=(196, 128, 24, 255))
    for poly in (top_face, side_face, front_face):
        d.line(poly + [poly[0]], fill=INK, width=int(2.8 * s), joint='curve')
    d.line(P([(78, 28), (114, 10)]), fill=(255, 255, 255, 160), width=int(2 * s))
    target.alpha_composite(layer)

draw_cheese(img)

# Soft drop shadow
shadow = Image.new('RGBA', (W, H), (0, 0, 0, 0))
ImageDraw.Draw(shadow).text((x + 2 * SCALE, y + 3 * SCALE), TEXT, font=font, fill=(0, 0, 0, 120), stroke_width=stroke, stroke_fill=(0, 0, 0, 120))
img.alpha_composite(shadow.filter(ImageFilter.GaussianBlur(2 * SCALE)))

# Thick dark outline
ImageDraw.Draw(img).text((x, y), TEXT, font=font, fill=INK, stroke_width=stroke, stroke_fill=INK)

# Letters filled with a vertical blue gradient, digits in cheese yellow
mask = Image.new('L', (W, H), 0)
ImageDraw.Draw(mask).text((x, y), TEXT, font=font, fill=255)
left, top, right, bottom = mask.getbbox()
split = x + ImageDraw.Draw(mask).textlength('Forum', font=font)
grad = Image.new('RGBA', (W, H))
gp = grad.load()
for j in range(H):
    t = min(1, max(0, (j - top) / max(1, bottom - top)))
    blue = (int(118 - 70 * t), int(196 - 86 * t), int(240 - 60 * t), 255)
    gold = (int(255 - 10 * t), int(214 - 70 * t), int(92 - 50 * t), 255)
    for i in range(W):
        gp[i, j] = gold if i >= split else blue
img.paste(grad, (0, 0), mask)

# Highlight on the upper half of the letters
hl_mask = Image.new('L', (W, H), 0)
ImageDraw.Draw(hl_mask).rectangle((0, top, W, top + (bottom - top) * 0.38), fill=255)
hl_mask = Image.composite(hl_mask, Image.new('L', (W, H), 0), mask.filter(ImageFilter.MinFilter(5)))
white = Image.new('RGBA', (W, H), (255, 255, 255, 70))
img.paste(white, (0, 0), hl_mask)

img = img.crop(img.getbbox())
img.save(OUT, optimize=True)
print('logo written', img.size, os.path.normpath(OUT))
