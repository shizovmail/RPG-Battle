#!/usr/bin/env python3
"""Tabel damage & heal RPG Battle dari stat bawaan (atau stat lain lewat variabel STAT).
Damage = max(1, round(Attack x persen - Defend target)). Target dianggap berHP penuh; tanpa Benteng/penjagaan tank/fighter.
Jalankan: python3 docs/tabel_damage.py > docs/TABEL-DAMAGE.md"""
import sys, os
sys.path.insert(0, os.path.dirname(__file__))
from simulasi_keseimbangan import STAT

NAMA = {'tank': 'Tank', 'fighter': 'Fighter', 'assassin': 'Assassin', 'mage': 'Mage', 'healer': 'Healer'}
ROLES = ['tank', 'fighter', 'assassin', 'mage', 'healer']
BASIC = {'tank': (.10, .20), 'fighter': (.15, .35), 'assassin': (.20, .50), 'mage': (.10, .30), 'healer': (.05, .25)}
# (penyerang, skill, rentang persen, catatan)
SKILL = [('tank', 'Serangan dasar', BASIC['tank']), ('fighter', 'Serangan dasar', BASIC['fighter']),
         ('fighter', 'Rentetan Pukulan - pukulan ke-5 (lawan kedua)', (.45, .65)),
         ('assassin', 'Serangan dasar', BASIC['assassin']), ('assassin', 'Tusukan Mematikan (skill 1)', (.90, 1.40)),
         ('assassin', 'Serangan Bayangan (skill 2, giliran ke-2)', (2.30, 2.80)),
         ('mage', 'Serangan dasar', BASIC['mage']), ('mage', 'Hujan Meteor / Badai Es (tiap lawan)', (.65, 1.00)),
         ('healer', 'Serangan dasar', BASIC['healer'])]


def dmg(atk, pct, df): return max(1, round(atk * pct - df))


def tabel_serangan():
    out = []
    for a, nm, (lo, hi) in SKILL:
        atk = STAT[a]['atk']
        out.append('### %s - %s (%d%%-%d%% x Attack %d)\n' % (NAMA[a], nm, round(lo * 100), round(hi * 100), atk))
        out.append('| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |')
        out.append('|---|---|---|---|---|---|---|')
        for t in ROLES:
            hp, df = STAT[t]['hp'], STAT[t]['df']
            d0, d1, dm = dmg(atk, lo, df), dmg(atk, hi, df), dmg(atk, (lo + hi) / 2, df)
            ko = '1 (KO langsung)' if dm >= hp else '%.1f' % (hp / dm)
            out.append('| %s | %d | %d | %d-%d (rata %d) | %d-%d%s | %d | %s |' % (
                NAMA[t], hp, df, d0, d1, dm, max(0, hp - d1), max(0, hp - d0), ' (KO)' if hp - d1 <= 0 else '', max(0, hp - dm), ko))
        out.append('')
    # kombo fighter: 4 pukulan, Defend dikurangkan sekali untuk total
    atk = STAT['fighter']['atk']
    out.append('### Fighter - Rentetan Pukulan, 4 pukulan pertama ke 1 lawan (tiap 15%%-30%%, total 60%%-120%% x Attack %d, Defend dipotong sekali)\n' % atk)
    out.append('| Target | HP | Def | Total 4 pukulan (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata |')
    out.append('|---|---|---|---|---|---|')
    for t in ROLES:
        hp, df = STAT[t]['hp'], STAT[t]['df']
        d0, d1, dm = dmg(atk, .60, df), dmg(atk, 1.20, df), dmg(atk, .90, df)
        out.append('| %s | %d | %d | %d-%d (rata %d) | %d-%d | %d |' % (NAMA[t], hp, df, d0, d1, dm, max(0, hp - d1), max(0, hp - d0), max(0, hp - dm)))
    out.append('')
    return '\n'.join(out)


def tabel_heal():
    out = ['### Healer - pemulihan ke teman (atau diri sendiri), kekuatan = Attack Healer %d\n' % STAT['healer']['atk'],
           'Pemulihan berhenti di HP maksimal. Kolom terakhir: HP setelah di-heal bila teman sedang sekarat dengan HP 20.\n',
           '| Skill | Persen | Jumlah pulih (min-maks, rata-rata) |', '|---|---|---|']
    atk = STAT['healer']['atk']
    for nm, (lo, hi) in (('Penyembuhan (skill 1, 1 teman)', (2.0, 2.5)), ('Hujan Cahaya (skill 2, tiap teman)', (.8, 1.0))):
        out.append('| %s | %d%%-%d%% | %d-%d (rata %d) |' % (nm, round(lo * 100), round(hi * 100), round(atk * lo), round(atk * hi), round(atk * (lo + hi) / 2)))
    out += ['', '| Teman | HP maks | Penyembuhan: % HP maks | HP setelah heal dari HP 20 | Hujan Cahaya: % HP maks | HP setelah heal dari HP 20 |', '|---|---|---|---|---|---|']
    for t in ROLES:
        hp = STAT[t]['hp']
        a0, a1, b0, b1 = round(atk * 2.0), round(atk * 2.5), round(atk * .8), round(atk * 1.0)
        out.append('| %s | %d | %d%%-%d%% | %d-%d | %d%%-%d%% | %d-%d |' % (NAMA[t], hp, round(100 * a0 / hp), round(100 * a1 / hp), min(hp, 20 + a0), min(hp, 20 + a1),
                                                                      round(100 * b0 / hp), round(100 * b1 / hp), min(hp, 20 + b0), min(hp, 20 + b1)))
    return '\n'.join(out) + '\n'


if __name__ == '__main__':
    print('# Tabel damage & heal (stat bawaan)\n')
    print('Stat: ' + ', '.join('%s HP %d / Atk %d / Def %d' % (NAMA[r], STAT[r]['hp'], STAT[r]['atk'], STAT[r]['df']) for r in ROLES) + '\n')
    print('Damage = max(1, round(Attack x persen - Defend target)). Semua target berHP penuh dan tanpa perlindungan (Benteng, Pasang Badan, Lompat Pelindung). '
          'Tank yang menjaga teman menerima damage persis seperti baris "Tank" (memakai Defend tank). Mage Kutukan, Benteng Tim, Pasang Badan, Lompat Pelindung tidak menimbulkan damage langsung.\n')
    print(tabel_serangan())
    print(tabel_heal())
