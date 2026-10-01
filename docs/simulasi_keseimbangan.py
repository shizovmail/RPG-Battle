#!/usr/bin/env python3
"""
Simulasi keseimbangan RPG Battle 4 vs 4.

Meniru aturan game (lihat games/inc/rpg.php) dengan pemain "acak": tiap pemain memilih skill yang
tersedia secara acak (skill utama lebih sering) dan target acak, lalu menjawab benar dengan peluang p.
Dipakai untuk memilih stat bawaan. Jalankan:  python3 docs/simulasi_keseimbangan.py
Opsi: python3 docs/simulasi_keseimbangan.py <giliran_maks> <jumlah_simulasi>
"""
import random, statistics as st, sys

ROLES = ['tank', 'assassin', 'mage', 'healer']
CD = {'tank': {'s1': 0, 's2': 2}, 'assassin': {'s1': 2, 's2': 3}, 'mage': {'s1': 3, 's2': 2}, 'healer': {'s1': 0, 's2': 3}}
BASIC, STRIKE, HEAL_ALL = 0.5, 2.75, 0.55
HEAL = 35
STAT = {  # sama dengan rpg_stat_bawaan() di PHP
    'tank': dict(hp=300, atk=24, df=12, heal=0),
    'assassin': dict(hp=140, atk=75, df=5, heal=0),
    'mage': dict(hp=160, atk=55, df=6, heal=0),
    'healer': dict(hp=170, atk=30, df=8, heal=HEAL),
}


def main_satu(S, p, seed, maks):
    rnd = random.Random(seed)
    U = [dict(t=t, r=r, hp=S[r]['hp'], mx=S[r]['hp'], atk=S[r]['atk'], df=S[r]['df'], heal=S[r]['heal'],
              cd={'s1': 0, 's2': 0}, siap=0, kut=0, dead=False) for t in (0, 1) for r in ROLES]
    dd = {r: 0 for r in ROLES}; hl = 0; ko = {r: 0 for r in ROLES}
    A = lambda t: [x for x in U if x['t'] == t and x['hp'] > 0]
    rounds = 0
    while rounds < maks and A(0) and A(1):
        rounds += 1
        acts = []
        for u in U:
            if u['hp'] <= 0: continue
            en, al, r = A(1 - u['t']), A(u['t']), u['r']
            if u['siap']:
                sk, tg = 'strike', rnd.choice(en)
            else:
                av = ['basic'] + [k for k in ('s1', 's2') if u['cd'][k] == 0]
                w = {'basic': 1, 's1': 4, 's2': 4}
                if r == 'healer':
                    w['s1'] = 6 if any(x['hp'] < x['mx'] * .7 for x in al) else .5
                    w['s2'] = 5 if sum(x['hp'] < x['mx'] * .8 for x in al) >= 2 else .5
                sk = rnd.choices(av, [w[a] for a in av])[0]
                if r in ('tank', 'healer') and sk == 's1': tg = rnd.choice(al)
                elif sk == 'basic' or (r == 'assassin' and sk == 's1') or (r == 'mage' and sk == 's2'): tg = rnd.choice(en)
                else: tg = None
            ok = rnd.random() < p
            if u['kut']:
                u['kut'] = 0
                if ok and rnd.random() < .75: ok = False
            acts.append([u, sk, tg, ok])
        shield, stealth, curses, guard = {}, set(), [], {}
        for u, sk, tg, ok in acts:  # cooldown dipakai walau gagal; Bayangan tanpa cooldown di tahap 1
            r = u['r']
            if sk in ('s1', 's2') and not (r == 'assassin' and sk == 's2'): u['cd'][sk] = CD[r][sk] + 1
            if sk == 'strike': u['siap'] = 0; u['cd']['s2'] = CD['assassin']['s2'] + 1
            if not ok: continue
            if r == 'tank' and sk == 's1':
                if tg is u: shield[id(u)] = min(shield.get(id(u), 1), .10)
                else: guard[id(tg)] = u          # tank berdiri di depan sekutu: sekutu 0 damage, tank terima 10%
            if r == 'tank' and sk == 's2':
                for x in A(u['t']): shield[id(x)] = min(shield.get(id(x), 1), .20)
            if r == 'assassin' and sk == 's2': stealth.add(id(u)); u['siap'] = 1
            if r == 'mage' and sk == 's2': curses.append(tg)
        for u, sk, tg, ok in acts:
            if ok and u['r'] == 'healer':
                tl = [tg] if sk == 's1' else A(u['t']); amt = u['heal'] if sk == 's1' else u['heal'] * HEAL_ALL
                for x in tl:
                    h = min(x['mx'], x['hp'] + amt) - x['hp']; x['hp'] += h; hl += h
        rnd.shuffle(acts)
        for u, sk, tg, ok in acts:
            if not ok: continue
            r = u['r']; dm = []
            if sk == 'basic': dm = [(tg, BASIC)]
            elif r == 'assassin' and sk == 's1': dm = [(tg, 1.0)]
            elif sk == 'strike': dm = [(tg, STRIKE)]
            elif r == 'mage' and sk == 's1': dm = [(x, 1.0) for x in A(1 - u['t'])]
            for x, m in dm:
                if id(x) in stealth: continue
                d = max(1, round(u['atk'] * m - x['df']))
                if id(x) in guard and guard[id(x)]['hp'] > 0:
                    x = guard[id(x)]; d = max(1, round(d * .10))
                else: d = max(1, round(d * shield.get(id(x), 1)))
                e = min(d, x['hp']); x['hp'] -= e; dd[r] += e
        for x in curses: x['kut'] = 1
        for u in U:
            for k in ('s1', 's2'):
                if u['cd'][k] > 0: u['cd'][k] -= 1
            if u['hp'] <= 0:
                u['siap'] = 0
                if not u['dead']: u['dead'] = True; ko[u['r']] += 1
    a0, a1 = len(A(0)), len(A(1))
    h0, h1 = sum(x['hp'] for x in A(0)), sum(x['hp'] for x in A(1))
    win = 0 if (a0 and not a1) else 1 if (a1 and not a0) else None
    if win is None: win = 0 if (a0, h0) > (a1, h1) else 1 if (a1, h1) > (a0, h0) else -1  # aturan "soal habis"
    return dict(rounds=rounds, wipe=not (a0 and a1), win=win, dd=dd, hl=hl, ko=ko)


def laporan(S, p, maks, n):
    R = [main_satu(S, p, i, maks) for i in range(n)]
    sisi = [sum(1 for r in R if r['win'] == k) / n for k in (0, 1, -1)]
    return ('p(benar)=%.1f | giliran rata-rata %.1f | tim tumbang total %2d%% | menang Kiri/Kanan/seri %2d%%/%2d%%/%2d%%' %
            (p, st.mean(r['rounds'] for r in R), 100 * sum(r['wipe'] for r in R) / n, *[round(100 * x) for x in sisi]),
            {k: round(st.mean(r['dd'][k] for r in R)) for k in ROLES},
            round(st.mean(r['hl'] for r in R)),
            {k: round(sum(r['ko'][k] for r in R) / (2 * n), 2) for k in ROLES})


if __name__ == '__main__':
    maks = int(sys.argv[1]) if len(sys.argv) > 1 else 14
    n = int(sys.argv[2]) if len(sys.argv) > 2 else 2000
    print('Stat bawaan:', STAT)
    print('Batas %d giliran (≈ soal habis), %d simulasi per baris\n' % (maks, n))
    for p in (0.5, 0.7, 0.9):
        head, dmg, heal, ko = laporan(STAT, p, maks, n)
        print(head); print('   damage rata-rata per peran per tim:', dmg, '| total heal:', heal)
        print('   peluang karakter tumbang per peran       :', ko)
