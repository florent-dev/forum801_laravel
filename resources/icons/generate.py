# Generates the interface icons in the style of the section icons:
# thick dark-brown outline, flat warm colors and a soft highlight.
# Run: python3 resources/icons/generate.py  (writes public/img/icons/*.svg)
import math, os

OUT = os.path.join(os.path.dirname(__file__), '..', '..', 'public', 'img', 'icons')
INK = '#2b1b0c'
O = f'stroke="{INK}" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"'
HL = 'fill="#ffffff" opacity=".45"'

C = dict(cream='#f4ead2', white='#ffffff', cheese='#f2c14e', cheese_d='#d99a2b', brown='#9a5a2a', brown_d='#6e3b17',
         red='#d9483b', red_d='#a8302a', teal='#2bb3a3', teal_d='#1e8679', steel='#93a9b6', steel_d='#64808f',
         mouse='#b9ab9d', mouse_d='#9b8c7e', pink='#f2a7a0', gold='#f5c542', gold_d='#c9921d', green='#6fbf4a',
         green_d='#4a8f2f', sky='#5bbde0', orange='#f08a2c', stone='#c9c1b3', stone_d='#a39a8b', purple='#9d6bd6')


def svg(body):
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40">{body}</svg>'


def poly(points):
    return ' '.join(f'{x:.2f},{y:.2f}' for x, y in points)


def star(cx, cy, r1, r2, n=5, rot=-90):
    pts = []
    for i in range(n * 2):
        r = r1 if i % 2 == 0 else r2
        a = math.radians(rot + i * 180 / n)
        pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return poly(pts)


def gear(cx, cy, ro, ri, teeth=8):
    pts = []
    step = 360 / (teeth * 4)
    for i in range(teeth * 4):
        r = ro if i % 4 in (1, 2) else ri
        a = math.radians(i * step - 90)
        pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return poly(pts)


def mouse(cx, cy, s=1.0):
    # A mouse head, the forum mascot
    e = 7 * s
    return (f'<circle cx="{cx - 9*s}" cy="{cy - 9*s}" r="{e}" fill="{C["mouse_d"]}" {O}/>'
            f'<circle cx="{cx + 9*s}" cy="{cy - 9*s}" r="{e}" fill="{C["mouse_d"]}" {O}/>'
            f'<circle cx="{cx - 9*s}" cy="{cy - 9*s}" r="{4*s}" fill="{C["pink"]}"/>'
            f'<circle cx="{cx + 9*s}" cy="{cy - 9*s}" r="{4*s}" fill="{C["pink"]}"/>'
            f'<ellipse cx="{cx}" cy="{cy}" rx="{12.5*s}" ry="{11.5*s}" fill="{C["mouse"]}" {O}/>'
            f'<circle cx="{cx - 4.5*s}" cy="{cy - 1.5*s}" r="{1.9*s}" fill="{INK}"/>'
            f'<circle cx="{cx + 4.5*s}" cy="{cy - 1.5*s}" r="{1.9*s}" fill="{INK}"/>'
            f'<circle cx="{cx}" cy="{cy + 3.5*s}" r="{2.1*s}" fill="{C["pink"]}" stroke="{INK}" stroke-width="{1.2*s}"/>'
            f'<path d="M{cx - 4*s} {cy + 4*s} l-7 {-1*s} M{cx - 4*s} {cy + 5.5*s} l-7 {1.5*s} M{cx + 4*s} {cy + 4*s} l7 {-1*s} M{cx + 4*s} {cy + 5.5*s} l7 {1.5*s}" stroke="{INK}" stroke-width="{0.9*s}" stroke-linecap="round"/>'
            f'<ellipse cx="{cx - 5*s}" cy="{cy - 7*s}" rx="{3.5*s}" ry="{1.8*s}" {HL}/>')


def shield(fill, inner):
    return (f'<path d="M20 3.5 L34.5 8.5 V18.5 C34.5 28 27.5 34 20 37 C12.5 34 5.5 28 5.5 18.5 V8.5 Z" fill="{fill}" {O}/>'
            f'<path d="M20 8 L30.5 11.7 V18.8 C30.5 25.6 25.6 30 20 32.3 C14.4 30 9.5 25.6 9.5 18.8 V11.7 Z" fill="{inner}"/>'
            f'<path d="M11 12.5 L20 9.3 V15 L11 18 Z" {HL}/>')


ICONS = {
    # Header
    'unread': (f'<path d="M6 8 H34 A4 4 0 0 1 38 12 V25 A4 4 0 0 1 34 29 H18 L10 35.5 V29 H6 A4 4 0 0 1 2 25 V12 A4 4 0 0 1 6 8 Z" fill="{C["white"]}" {O}/>'
               f'<path d="M9 15 H27 M9 21 H22" stroke="{C["brown"]}" stroke-width="2.4" stroke-linecap="round"/>'
               f'<circle cx="32" cy="9" r="6.5" fill="{C["cheese"]}" {O}/><circle cx="30.2" cy="7.3" r="2" {HL}/>'),
    'messages': (f'<rect x="3" y="9" width="34" height="23" rx="3" fill="{C["cream"]}" {O}/>'
                 f'<path d="M3.8 10.5 L20 23 L36.2 10.5" fill="none" {O}/>'
                 f'<circle cx="20" cy="23" r="5" fill="{C["red"]}" {O}/><circle cx="18.6" cy="21.6" r="1.5" {HL}/>'),
    'moderation': shield(C['teal_d'], C['teal']) + f'<path d="M13.5 24 L26.5 17 L27.5 25.5 Z" fill="{C["cheese"]}" {O}/><circle cx="21" cy="22.5" r="1.4" fill="{C["cheese_d"]}"/><circle cx="24.7" cy="21.4" r="1" fill="{C["cheese_d"]}"/>',
    'notifications': (f'<circle cx="20" cy="5.5" r="2.4" fill="{C["gold_d"]}" {O}/>'
                      f'<path d="M20 7 C12.5 7 9.5 12.5 9.5 19 V24.5 L5.5 29.5 H34.5 L30.5 24.5 V19 C30.5 12.5 27.5 7 20 7 Z" fill="{C["gold"]}" {O}/>'
                      f'<circle cx="20" cy="32.5" r="3.6" fill="{C["brown"]}" {O}/>'
                      f'<path d="M13.5 13 C14.8 10.5 16.8 9.5 18.5 9.3" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".55" fill="none"/>'),
    'help': (f'<circle cx="20" cy="20" r="16" fill="{C["cream"]}" {O}/>'
             f'<path d="M14.5 15.5 A5.6 5.6 0 1 1 22.8 20.4 C21 21.4 20 22.4 20 24.8" fill="none" stroke="{C["brown_d"]}" stroke-width="3.6" stroke-linecap="round"/>'
             f'<circle cx="20" cy="30.2" r="2.3" fill="{C["brown_d"]}"/><ellipse cx="13" cy="10.5" rx="4" ry="2" {HL}/>'),
    'language': (f'<circle cx="20" cy="20" r="16" fill="{C["sky"]}" {O}/>'
                 f'<path d="M9 13 C12 11 15 12 16 15 C17 18 14 19 14 22 C14 25 11 25 9.5 23 C7 20 7.5 15 9 13 Z" fill="{C["green"]}" stroke="{C["green_d"]}" stroke-width="1.2"/>'
                 f'<path d="M23 8.5 C27 8 31 11 32 14 C30 16 27 15 25 17 C23 19 25 22 28 23 C30 24 30 28 27 30 C24 31 22 28 22 25 C22 22 19 21 20 17 C21 14 21 10 23 8.5 Z" fill="{C["green"]}" stroke="{C["green_d"]}" stroke-width="1.2"/>'
                 f'<ellipse cx="14" cy="10" rx="4.5" ry="2" {HL}/>'),
    'search': (f'<path d="M24.5 24.5 L34.5 34.5" stroke="{INK}" stroke-width="8" stroke-linecap="round"/>'
               f'<path d="M24.5 24.5 L34.5 34.5" stroke="{C["brown"]}" stroke-width="4.4" stroke-linecap="round"/>'
               f'<circle cx="16.5" cy="16.5" r="11" fill="{C["sky"]}" stroke="{INK}" stroke-width="2.2"/>'
               f'<circle cx="16.5" cy="16.5" r="11" fill="none" stroke="{C["brown_d"]}" stroke-width="1.6" opacity=".55"/>'
               f'<path d="M10.5 13 A7 7 0 0 1 16 9.5" stroke="#fff" stroke-width="2.6" stroke-linecap="round" fill="none" opacity=".7"/>'),
    # Account
    'profile': mouse(20, 22),
    'friends': mouse(13.5, 24, .72) + mouse(26.5, 20, .72),
    'users': mouse(10.5, 25, .6) + mouse(29.5, 25, .6) + mouse(20, 21, .72),
    'settings': (f'<polygon points="{gear(20, 20, 16.5, 12.5)}" fill="{C["gold_d"]}" {O}/>'
                 f'<circle cx="20" cy="20" r="9" fill="{C["gold"]}" {O}/>'
                 f'<circle cx="20" cy="20" r="3.8" fill="{C["brown_d"]}" {O}/><ellipse cx="16" cy="15.5" rx="3" ry="1.6" {HL}/>'),
    'bookmarks': (f'<path d="M11 4 H29 A2 2 0 0 1 31 6 V36 L20 28.5 L9 36 V6 A2 2 0 0 1 11 4 Z" fill="{C["red"]}" {O}/>'
                  f'<path d="M13.5 8 V24" stroke="#fff" stroke-width="2.6" stroke-linecap="round" opacity=".45"/>'),
    'followed': (f'<polygon points="{star(20, 21, 16.5, 7.5)}" fill="{C["gold"]}" {O}/>'
                 f'<ellipse cx="17" cy="15" rx="3" ry="1.6" {HL}/>'),
    'logout': (f'<rect x="9" y="4" width="22" height="32" rx="2" fill="{C["brown"]}" {O}/>'
               f'<rect x="13" y="8" width="14" height="10" rx="1.5" fill="{C["brown_d"]}" opacity=".6"/>'
               f'<rect x="13" y="21" width="14" height="11" rx="1.5" fill="{C["brown_d"]}" opacity=".6"/>'
               f'<circle cx="26" cy="21" r="2.2" fill="{C["gold"]}" {O}/><path d="M12 6 V34" stroke="#fff" stroke-width="1.6" opacity=".35"/>'),
    'privacy': (f'<path d="M12.5 18 V13 A7.5 7.5 0 0 1 27.5 13 V18" fill="none" stroke="{INK}" stroke-width="7"/>'
                f'<path d="M12.5 18 V13 A7.5 7.5 0 0 1 27.5 13 V18" fill="none" stroke="{C["steel"]}" stroke-width="3.6"/>'
                f'<rect x="7.5" y="17" width="25" height="19" rx="3" fill="{C["gold"]}" {O}/>'
                f'<circle cx="20" cy="25" r="3" fill="{C["brown_d"]}"/><path d="M20 26 V31" stroke="{C["brown_d"]}" stroke-width="2.6" stroke-linecap="round"/>'
                f'<path d="M10.5 20 H18" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".5"/>'),
    'account': (f'<path d="M17 20 L35 20 V25 M29 20 V24.5" fill="none" stroke="{INK}" stroke-width="6.6" stroke-linecap="round" stroke-linejoin="round"/>'
                f'<path d="M17 20 L35 20 V25 M29 20 V24.5" fill="none" stroke="{C["gold"]}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
                f'<circle cx="12" cy="20" r="8" fill="{C["gold"]}" {O}/><circle cx="11" cy="20" r="2.8" fill="{C["brown_d"]}"/>'
                f'<ellipse cx="9.5" cy="15.5" rx="2.6" ry="1.4" {HL}/>'),
    # Administration
    'admin': (f'<path d="M12 33 L27 18" stroke="{INK}" stroke-width="7.4" stroke-linecap="round"/>'
              f'<path d="M12 33 L27 18" stroke="{C["brown"]}" stroke-width="3.6" stroke-linecap="round"/>'
              f'<path d="M18.5 9.5 L27 3.8 L36.2 13 L30.5 21.5 Z" fill="{C["steel"]}" {O}/>'
              f'<path d="M21.5 9.2 L27 5.6" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".55"/>'),
    'security': shield(C['steel_d'], C['steel']) + f'<circle cx="20" cy="18" r="3.6" fill="{INK}"/><path d="M18.4 19.5 L17 27 H23 L21.6 19.5 Z" fill="{INK}"/>',
    'maintenance': (f'<path d="M6 34 H34" stroke="{INK}" stroke-width="2.2" stroke-linecap="round"/>'
                    f'<path d="M15.5 6 H24.5 L32 33 H8 Z" fill="{C["orange"]}" {O}/>'
                    f'<path d="M13.4 14 H26.6 L28.3 20 H11.7 Z" fill="#fff"/><path d="M10.6 24.5 H29.4 L30.8 29.5 H9.2 Z" fill="#fff"/>'
                    f'<path d="M15.5 6 H24.5 L32 33 H8 Z" fill="none" {O}/>'),
    'toggles': (f'<rect x="4" y="6" width="32" height="12" rx="6" fill="{C["green"]}" {O}/><circle cx="29.5" cy="12" r="4.3" fill="#fff" {O}/>'
                f'<rect x="4" y="22" width="32" height="12" rx="6" fill="{C["stone"]}" {O}/><circle cx="10.5" cy="28" r="4.3" fill="#fff" {O}/>'
                f'<path d="M8 10 H18" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".5"/>'),
    'filter': (f'<path d="M4 6 H36 L24 21 V33 L16 37 V21 Z" fill="{C["cheese"]}" {O}/>'
               f'<path d="M8 9 H20" stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".55"/>'
               f'<circle cx="31" cy="30" r="6.5" fill="{C["red"]}" {O}/><path d="M27.5 30 H34.5" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>'),
    'health': (f'<path d="M20 35 C9 27 3.5 21 3.5 13.5 A8 8 0 0 1 20 9.5 A8 8 0 0 1 36.5 13.5 C36.5 21 31 27 20 35 Z" fill="{C["red"]}" {O}/>'
               f'<path d="M6 19 H13 L16 13 L20 25 L23 17 L25 19 H34" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>'
               f'<ellipse cx="11" cy="11.5" rx="3" ry="1.6" {HL}/>'),
    'data': (f'<path d="M5 13 L20 6 L35 13 V29 L20 36 L5 29 Z" fill="{C["brown"]}" {O}/>'
             f'<path d="M5 13 L20 20 L35 13 M20 20 V36" fill="none" {O}/><path d="M5 13 L20 20 V36 L5 29 Z" fill="{C["brown_d"]}" opacity=".35"/>'
             f'<path d="M12.5 9.5 L27.5 16.5 V22" fill="none" stroke="{C["cream"]}" stroke-width="3" stroke-linecap="round"/>'),
    'roles': (f'<path d="M5.5 30 L4 11.5 L13 19 L20 7 L27 19 L36 11.5 L34.5 30 Z" fill="{C["gold"]}" {O}/>'
              f'<rect x="5.5" y="29" width="29" height="5.5" rx="1.5" fill="{C["gold_d"]}" {O}/>'
              f'<circle cx="20" cy="22" r="2.6" fill="{C["red"]}" {O}/><circle cx="12" cy="24" r="1.9" fill="{C["teal"]}" {O}/><circle cx="28" cy="24" r="1.9" fill="{C["purple"]}" {O}/>'),
    'sections': (''.join(f'<rect x="{x}" y="{y}" width="13" height="13" rx="3" fill="{c}" {O}/><rect x="{x+2.5}" y="{y+2.5}" width="5" height="2" rx="1" {HL}/>'
                         for (x, y), c in zip([(5, 5), (22, 5), (5, 22), (22, 22)], [C['cream'], C['cheese'], C['teal'], C['red']]))),
    'reports': (f'<path d="M9 36 V5" stroke="{INK}" stroke-width="5.4" stroke-linecap="round"/><path d="M9 36 V5" stroke="{C["brown"]}" stroke-width="2.4" stroke-linecap="round"/>'
                f'<path d="M10.5 6 C16 3.5 20 9.5 25.5 7 C28.5 5.8 31 5.5 34 6.5 V21 C31 20 28.5 20.3 25.5 21.5 C20 24 16 18 10.5 20.5 Z" fill="{C["red"]}" {O}/>'
                f'<path d="M13 9 C16 8 18 9.5 21 10.3" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".5" fill="none"/>'),
    'trash': (f'<path d="M9 12 H31 L28.8 35 A2 2 0 0 1 26.8 36.8 H13.2 A2 2 0 0 1 11.2 35 Z" fill="{C["steel"]}" {O}/>'
              f'<rect x="6" y="8" width="28" height="5" rx="2" fill="{C["steel_d"]}" {O}/><path d="M16 8 V5.5 H24 V8" fill="none" {O}/>'
              f'<path d="M16 17 V31 M20 17 V31 M24 17 V31" stroke="{C["steel_d"]}" stroke-width="2" stroke-linecap="round"/>'),
    # Forum
    'guilds': (f'<path d="M20 3 V11" stroke="{INK}" stroke-width="2"/><path d="M20 3.5 L28 6 L20 8.5 Z" fill="{C["red"]}" {O}/>'
               f'<path d="M7 36 V16 H11 V12 H15 V16 H18 V12 H22 V16 H25 V12 H29 V16 H33 V36 Z" fill="{C["stone"]}" {O}/>'
               f'<path d="M16 36 V28 A4 4 0 0 1 24 28 V36 Z" fill="{C["brown_d"]}" {O}/>'
               f'<rect x="11" y="20" width="4" height="5" rx="1" fill="{C["brown_d"]}"/><rect x="25" y="20" width="4" height="5" rx="1" fill="{C["brown_d"]}"/>'
               f'<path d="M9.5 18.5 V33" stroke="#fff" stroke-width="2" opacity=".4"/>'),
    'new-thread': (f'<path d="M7 33 C12 26 22 13 33 5 C35 12 30 22 17 27 L12 30 Z" fill="{C["white"]}" {O}/>'
                   f'<path d="M7 33 L24 15" stroke="{C["brown"]}" stroke-width="1.8" stroke-linecap="round"/>'
                   f'<circle cx="29.5" cy="29.5" r="7" fill="{C["green"]}" {O}/><path d="M29.5 25.8 V33.2 M25.8 29.5 H33.2" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>'),
    'poll': (f'<rect x="4" y="5" width="32" height="30" rx="3" fill="{C["cream"]}" {O}/>'
             f'<rect x="10" y="20" width="5" height="10" rx="1" fill="{C["teal"]}" {O}/><rect x="17.5" y="12" width="5" height="18" rx="1" fill="{C["cheese"]}" {O}/>'
             f'<rect x="25" y="16" width="5" height="14" rx="1" fill="{C["red"]}" {O}/>'),
}

os.makedirs(OUT, exist_ok=True)
for name, body in ICONS.items():
    with open(os.path.join(OUT, f'{name}.svg'), 'w') as f:
        f.write(svg(body))
print(len(ICONS), 'icons written to', os.path.normpath(OUT))
