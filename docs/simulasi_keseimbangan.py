#!/usr/bin/env python3
"""
Simulasi keseimbangan RPG Battle (4 vs 4 atau 5 vs 5 dengan Fighter).

Meniru aturan game (games/inc/rpg.php). Dua gaya pemain:
  acak     : skill & target dipilih acak (skill utama lebih sering) -> batas bawah kualitas bermain
  terarah  : menyerang 2 lawan terlemah (acak di antaranya), tank/healer/fighter melindungi 2 sekutu terlemah
Jawaban benar dengan peluang p. Pertandingan berakhir bila satu tim habis atau giliran maksimum tercapai
(= soal habis), lalu pemenang: karakter hidup lebih banyak, bila sama total HP lebih besar.

Jalankan:  python3 docs/simulasi_keseimbangan.py [giliran_maks] [jumlah_simulasi]
"""
import random, statistics as st, sys

BASE = ['tank', 'assassin', 'mage', 'healer']
CD = {'tank': {'s1': 0, 's2': 2}, 'fighter': {'s1': 2, 's2': 2}, 'assassin': {'s1': 2, 's2': 3},
      'mage': {'s1': 3, 's2': 2}, 'healer': {'s1': 0, 's2': 3}}
STRIKE, HEAL_ALL = 2.75, 0.55
STAT = {  # sama dengan rpg_stat_bawaan() di PHP
    'tank': dict(hp=240, atk=24, df=12, heal=0),
    'fighter': dict(hp=175, atk=48, df=9, heal=0),
    'assassin': dict(hp=110, atk=75, df=5, heal=0),
    'mage': dict(hp=125, atk=55, df=6, heal=0),
    'healer': dict(hp=135, atk=30, df=8, heal=30),
}


def main_satu(S, p, seed, maks, fighter, gaya):
    rnd = random.Random(seed)
    roles = BASE[:1] + (['fighter'] if fighter else []) + BASE[1:]
    U = [dict(t=t, r=r, hp=S[r]['hp'], mx=S[r]['hp'], atk=S[r]['atk'], df=S[r]['df'], heal=S[r]['heal'],
              cd={'s1': 0, 's2': 0}, siap=0, kut=0, dead=False) for t in (0, 1) for r in roles]
    A = lambda t: [x for x in U if x['t'] == t and x['hp'] > 0]
    rounds = 0
    while rounds < maks and A(0) and A(1):
        rounds += 1
        acts = []
        for u in U:
            if u['hp'] <= 0: continue
            en, al, r = A(1 - u['t']), A(u['t']), u['r']
            if not en: continue
            tg = None
            if u['siap']:
                sk = 'strike'; tg = rnd.choice(sorted(en, key=lambda x: x['hp'])[:2]) if gaya == 'terarah' else rnd.choice(en)
            else:
                av = ['basic'] + [k for k in ('s1', 's2') if u['cd'][k] == 0]
                w = {'basic': 1, 's1': 4, 's2': 4}
                if r == 'healer':
                    w['s1'] = 6 if any(x['hp'] < x['mx'] * .7 for x in al) else .5
                    w['s2'] = 5 if sum(x['hp'] < x['mx'] * .8 for x in al) >= 2 else .5
                sk = rnd.choices(av, [w[a] for a in av])[0]
                lemah = lambda L: rnd.choice(sorted(L, key=lambda x: x['hp'] / x['mx'])[:2])
                if r == 'tank' and sk == 's1':
                    lain = [x for x in al if x is not u]
                    if not lain: sk = 'basic'
                    else: tg = lemah(lain) if gaya == 'terarah' else rnd.choice(lain)
                elif r in ('healer', 'fighter') and sk == 's1': tg = lemah(al) if gaya == 'terarah' else rnd.choice(al)
                elif sk == 'basic' or (r in ('assassin', 'fighter') and sk in ('s1', 's2')) or (r == 'mage' and sk == 's2'):
                    if r == 'fighter' and sk == 's1': tg = rnd.choice(al)
                    else: tg = (rnd.choice(sorted(en, key=lambda x: x['hp'])[:2]) if gaya == 'terarah' else rnd.choice(en))
            if sk == 'basic' and tg is None: tg = rnd.choice(sorted(en, key=lambda x: x['hp'])[:2]) if gaya == 'terarah' else rnd.choice(en)
            ok = rnd.random() < p
            if u['kut']:
                u['kut'] = 0
                if ok and rnd.random() < .75: ok = False
            acts.append([u, sk, tg, ok])
        benteng, stealth, curses, tguard, fguard, fself = {}, set(), [], {}, {}, set()
        for u, sk, tg, ok in acts:
            r = u['r']
            if sk in ('s1', 's2') and not (r == 'assassin' and sk == 's2'): u['cd'][sk] = CD[r][sk] + 1
            if sk == 'strike': u['siap'] = 0; u['cd']['s2'] = CD['assassin']['s2'] + 1
            if not ok: continue
            if r == 'tank' and sk == 's1': tguard[id(tg)] = u
            if r == 'tank' and sk == 's2':
                for x in A(u['t']): benteng[id(x)] = .20
            if r == 'fighter' and sk == 's1':
                if tg is u: fself.add(id(u))
                else: fguard[id(tg)] = u
            if r == 'assassin' and sk == 's2': stealth.add(id(u)); u['siap'] = 1
            if r == 'mage' and sk == 's2': curses.append(tg)
        for u, sk, tg, ok in acts:
            if ok and u['r'] == 'healer':
                tl = [tg] if sk == 's1' else A(u['t']); amt = u['heal'] if sk == 's1' else u['heal'] * HEAL_ALL
                for x in tl:
                    if x['hp'] > 0: x['hp'] = min(x['mx'], x['hp'] + amt)
        stat = dict(dd=0)

        def kena(x, d):
            e = min(d, x['hp']); x['hp'] -= e

        def serangan(x, raw):
            """raw = damage yang tertuju ke x (selisih attack-defend sudah dihitung)."""
            raw = max(1, round(raw * benteng.get(id(x), 1)))
            gt = tguard.get(id(x))
            if gt is not None and gt['hp'] > 0 and gt is not x:
                kena(gt, max(1, round(raw * .10))); return
            fg = fguard.get(id(x))
            if fg is not None and fg['hp'] > 0 and fg is not x:
                kena(x, max(1, round(raw * .20)))
                bagian = max(1, round(raw * .35))
                gt2 = tguard.get(id(fg))
                if gt2 is not None and gt2['hp'] > 0: kena(gt2, max(1, round(bagian * .10)))
                else: kena(fg, bagian)
                return
            if id(x) in fself: raw = max(1, round(raw * .20))
            kena(x, raw)
        rnd.shuffle(acts)
        for u, sk, tg, ok in acts:
            if not ok: continue
            r = u['r']
            bm = rnd.uniform(.10, .20)
            if sk == 'basic':
                if id(tg) not in stealth and tg['hp'] > 0: serangan(tg, max(1, round(u['atk'] * bm - tg['df'])))
            elif r == 'assassin' and sk == 's1':
                if id(tg) not in stealth and tg['hp'] > 0: serangan(tg, max(1, round(u['atk'] - tg['df'])))
            elif sk == 'strike':
                if id(tg) not in stealth and tg['hp'] > 0: serangan(tg, max(1, round(u['atk'] * STRIKE - tg['df'])))
            elif r == 'mage' and sk == 's1':
                for x in A(1 - u['t']):
                    if id(x) not in stealth: serangan(x, max(1, round(u['atk'] - x['df'])))
            elif r == 'fighter' and sk == 's2':
                if id(tg) not in stealth and tg['hp'] > 0:
                    total = max(4, round(u['atk'] * .80 - tg['df']))      # 4 pukulan (20% x4): selisih defend dihitung sekali
                    for _ in range(4): serangan(tg, max(1, round(total / 4)))
                lain = [x for x in A(1 - u['t']) if x is not tg] or [tg]
                t2 = rnd.choice(lain)
                if id(t2) not in stealth and t2['hp'] > 0: serangan(t2, max(1, round(u['atk'] * .55 - t2['df'])))
        for x in curses: x['kut'] = 1
        for u in U:
            for k in ('s1', 's2'):
                if u['cd'][k] > 0: u['cd'][k] -= 1
            if u['hp'] <= 0:
                u['siap'] = 0
                u['dead'] = True
    a0, a1 = len(A(0)), len(A(1))
    h0, h1 = sum(x['hp'] for x in A(0)), sum(x['hp'] for x in A(1))
    if a0 == 0 and a1 == 0: how, win = 'seri', -1
    elif a1 == 0: how, win = 'habis', 0
    elif a0 == 0: how, win = 'habis', 1
    elif a0 != a1: how, win = 'hidup', 0 if a0 > a1 else 1
    elif h0 != h1: how, win = 'hp', 0 if h0 > h1 else 1
    else: how, win = 'seri', -1
    mati = {}
    for u in U:
        if u['dead']: mati[u['r']] = mati.get(u['r'], 0) + 1
    return dict(rounds=rounds, win=win, how=how, mati=mati, n=len(roles))


def laporan(S, p, maks, n, fighter, gaya):
    R = [main_satu(S, p, i, maks, fighter, gaya) for i in range(n)]
    pct = lambda f: round(100 * sum(1 for r in R if f(r)) / n)
    roles = BASE[:1] + (['fighter'] if fighter else []) + BASE[1:]
    ko = {k: round(sum(r['mati'].get(k, 0) for r in R) / (2 * n), 2) for k in roles}
    return dict(giliran=round(st.mean(r['rounds'] for r in R), 1),
                kiri=pct(lambda r: r['win'] == 0), kanan=pct(lambda r: r['win'] == 1),
                habis=pct(lambda r: r['how'] == 'habis'), hidup=pct(lambda r: r['how'] == 'hidup'),
                hp=pct(lambda r: r['how'] == 'hp'), seri=pct(lambda r: r['how'] == 'seri'), ko=ko)


if __name__ == '__main__':
    maks = int(sys.argv[1]) if len(sys.argv) > 1 else 30
    n = int(sys.argv[2]) if len(sys.argv) > 2 else 1500
    print('Stat bawaan:', {k: tuple(v.values()) for k, v in STAT.items()}, '(hp, atk, def, heal)')
    print('Giliran maksimum %d (= soal habis), %d simulasi per baris, peluang benar p\n' % (maks, n))
    for fighter in (False, True):
        for gaya in ('acak', 'terarah'):
            print('== %s | pemain %s' % ('5 vs 5 (dengan Fighter)' if fighter else '4 vs 4', gaya))
            for p in (0.5, 0.7, 0.9):
                r = laporan(STAT, p, maks, n, fighter, gaya)
                print('  p=%.1f giliran %4.1f | menang Sky/Dark %2d%%/%2d%% | via habis %2d%% · selisih hidup %2d%% · selisih HP %2d%% · seri %d%%' %
                      (p, r['giliran'], r['kiri'], r['kanan'], r['habis'], r['hidup'], r['hp'], r['seri']))
            print('     karakter tumbang per peran (p=0.9):', r['ko'])
