#!/usr/bin/env python3
"""
Simulasi keseimbangan RPG Battle (4 vs 4 atau 5 vs 5 dengan Fighter).

Meniru aturan game (games/inc/rpg.php), termasuk semua persen damage/heal yang diacak. Dua gaya pemain:
  acak     : skill & target dipilih acak (skill utama lebih sering) -> batas bawah kualitas bermain
  terarah  : menyerang 2 lawan terlemah (acak di antaranya), tank/healer/fighter melindungi 2 sekutu terlemah
Jawaban benar dengan peluang p. Pertandingan berakhir bila satu tim habis atau giliran maksimum tercapai
(= soal habis), lalu pemenang: karakter hidup lebih banyak, bila sama total HP lebih besar.

Laporan: persentase cara menang, serta per peran: kill akhir, damage, seberapa cepat pingsan, dan daya tahan.

Jalankan:  python3 docs/simulasi_keseimbangan.py [giliran_maks] [jumlah_simulasi]
"""
import random, statistics as st, sys

BASE = ['tank', 'assassin', 'mage', 'healer']
CD = {'tank': {'s1': 0, 's2': 2}, 'fighter': {'s1': 2, 's2': 2}, 'assassin': {'s1': 2, 's2': 3},
      'mage': {'s1': 2, 's2': 2}, 'healer': {'s1': 0, 's2': 3}}
BASIC = {r: (.15, .25) for r in ('tank', 'fighter', 'assassin', 'mage', 'healer')}   # Serangan Dasar sama untuk semua
STAT = {  # sama dengan rpg_stat_bawaan() di PHP: hp, attack, defend (kekuatan heal Healer = Attack-nya)
    'tank': dict(hp=330, atk=24, df=12),
    'fighter': dict(hp=250, atk=56, df=9),
    'assassin': dict(hp=225, atk=75, df=2),
    'mage': dict(hp=229, atk=65, df=4),
    'healer': dict(hp=233, atk=50, df=6),
}
ASN1, STRIKE = (.90, 1.40), (2.30, 2.80)   # Assassin skill 1 / Serangan Bayangan
HEAL1, HEAL2 = (1.0, 1.25), (.40, .50)      # Penyembuhan / Hujan Cahaya (kali Attack healer)
INISIATIF = False                             # False = serangan serempak (bawaan game); True = urutan acak menentukan siapa sempat menyerang
BENTENG = (.60, .70)                          # Benteng Tim: damage masuk (dikurangi acak 30-40%)
F_TEMAN, F_DIRI, F_HINDAR = (.05, .25), (.50, .70), (.05, .25)   # Lompat Pelindung


def main_satu(S, p, seed, maks, fighter, gaya, noheal=False, fk=None):
    """fk (uji pengeroyokan): dict(role=peran korban tim 1, n=jumlah penyerang tim 2, heal/tank/fighter=bool bantuan sekutu)."""
    rnd = random.Random(seed)
    U_ = lambda r: rnd.uniform(*r)
    roles = BASE[:1] + (['fighter'] if fighter else []) + BASE[1:]
    U = [dict(t=t, r=r, hp=S[r]['hp'], mx=S[r]['hp'], atk=S[r]['atk'], df=S[r]['df'], heal=S[r]['atk'], pulih=0,
              cd={'s1': 0, 's2': 0}, siap=0, kut=0, mati=None, dmg=0, kill=0) for t in (0, 1) for r in roles]
    A = lambda t: [x for x in U if x['t'] == t and x['hp'] > 0]
    V = next(x for x in U if x['t'] == 0 and x['r'] == fk['role']) if fk else None
    ATT = sorted([x for x in U if x['t'] == 1], key=lambda x: -x['atk'])[:fk['n']] if fk else []
    pertama = {}
    rounds = 0
    while rounds < maks and A(0) and A(1) and (not fk or V['hp'] > 0):
        rounds += 1
        acts = []
        lemah2 = lambda L: rnd.choice(sorted(L, key=lambda x: x['hp'] / x['mx'])[:2])
        for u in U:
            if u['hp'] <= 0: continue
            en, al, r = A(1 - u['t']), A(u['t']), u['r']
            if not en: continue
            tg = None
            if fk and u['t'] == 0:             # tim korban: heal/lindungi korban bila diizinkan, selain itu serangan dasar
                sk, tg = 'basic', rnd.choice(en)
                if r == 'healer' and fk['heal']: sk, tg = 's1', V
                elif r == 'tank' and fk['tank'] and u is not V: sk, tg = 's1', V
                elif r == 'fighter' and fk['fighter'] and u is not V and u['cd']['s1'] == 0: sk, tg = 's1', V
                acts.append([u, sk, tg, rnd.random() < p]); continue
            if fk and u not in ATT: continue    # tim penyerang: hanya n penyerang yang beraksi
            pilih_en = (lambda: rnd.choice(sorted(en, key=lambda x: x['hp'])[:2])) if gaya == 'terarah' else (lambda: rnd.choice(en))
            if fk and u['t'] == 1:
                pilih_en = lambda: V
            if gaya == 'fokus' and u['t'] == 1:      # uji: seluruh tim 2 mengeroyok Tank tim 1
                pilih_en = lambda: U[0] if U[0]['hp'] > 0 else rnd.choice(en)
            if u['siap']:
                sk = 'strike'; tg = pilih_en()
            else:
                av = ['basic'] + [k for k in ('s1', 's2') if u['cd'][k] == 0]
                w = {'basic': 1, 's1': 4, 's2': 4}
                if r == 'healer':
                    w['s1'] = 6 if any(x['hp'] < x['mx'] * .7 for x in al) else .5
                    w['s2'] = 5 if sum(x['hp'] < x['mx'] * .8 for x in al) >= 2 else .5
                sk = rnd.choices(av, [w[a] for a in av])[0]
                pilih_al = (lambda L: lemah2(L)) if gaya == 'terarah' else (lambda L: rnd.choice(L))
                if r == 'tank' and sk == 's1':
                    lain = [x for x in al if x is not u]
                    if not lain: sk = 'basic'
                    else: tg = pilih_al(lain)
                elif r in ('healer', 'fighter') and sk == 's1': tg = pilih_al(al)
                elif sk == 'basic' or (r in ('assassin', 'fighter') and sk in ('s1', 's2')) or (r == 'mage' and sk == 's2'):
                    tg = pilih_en()
            if sk == 'basic' and tg is None: tg = pilih_en()
            ok = rnd.random() < p
            acts.append([u, sk, tg, ok])
        # Kutukan Mage serempak: target yang terkena gagal; mage yang dikutuk mage lain kutukannya gagal, kecuali saling kutuk (keduanya terkutuk)
        ck = {id(a[0]): a for a in acts if a[0]['r'] == 'mage' and a[1] == 's2' and a[3] and a[2] is not None}
        berlaku = []
        for cid, a in ck.items():
            dikutuk = any(b[2] is a[0] for b in ck.values())
            saling = id(a[2]) in ck and ck[id(a[2])][2] is a[0]
            if dikutuk and not saling: continue
            berlaku.append(a[2])
        for tgt in berlaku:
            for b in acts:
                if b[0] is tgt: b[3] = False
        benteng, stealth, curses, tguard, fguard, fself = {}, set(), [], {}, {}, set()
        for u, sk, tg, ok in acts:
            r = u['r']
            if sk in ('s1', 's2') and not (r == 'assassin' and sk == 's2'): u['cd'][sk] = CD[r][sk] + 1
            if sk == 'strike': u['siap'] = 0; u['cd']['s2'] = CD['assassin']['s2'] + 1
            if not ok: continue
            if r == 'tank' and sk == 's1': tguard[id(tg)] = u
            if r == 'tank' and sk == 's2':
                for x in A(u['t']): benteng[id(x)] = U_(BENTENG)
            if r == 'fighter' and sk == 's1':
                if tg is u: fself.add(id(u))
                else: fguard[id(tg)] = u
            if r == 'assassin' and sk == 's2': stealth.add(id(u)); u['siap'] = 1
        for u, sk, tg, ok in acts:
            if ok and u['r'] == 'healer' and not noheal:
                tl = [tg] if sk == 's1' else A(u['t'])
                for x in tl:
                    if x['hp'] > 0:
                        amt = max(1, round(u['heal'] * (U_(HEAL1) if sk == 's1' else U_(HEAL2))))
                        e = min(x['mx'], x['hp'] + amt) - x['hp']; x['hp'] += e; u['pulih'] += e

        def kena(x, d, src):
            e = min(d, x['hp']); x['hp'] -= e; src['dmg'] += e
            if x['hp'] <= 0 and e > 0 and x['mati'] is None:
                x['mati'] = rounds; src['kill'] += 1
                pertama.setdefault(x['t'], x)

        def serangan(x, gross, k, src):
            """gross = attack x persen; k = bagian defend yang dipotong (1 = normal). Urutan: Benteng -> tank menerima 100% ->
            fighter melompat (teman 5-25%, fighter 50-70%) -> fighter menghindar. Tiap penerima memakai defend-nya sendiri."""
            net = lambda y: max(1, round(gross - y['df'] * k))
            pr = benteng.get(id(x), 1)
            mul = lambda v: max(1, round(v * pr))
            gt = tguard.get(id(x))
            if gt is not None and gt['hp'] > 0 and gt is not x:
                kena(gt, mul(net(gt)), src); return
            fg = fguard.get(id(x))
            if fg is not None and fg['hp'] > 0 and fg is not x:
                kena(x, max(1, round(mul(net(x)) * U_(F_TEMAN))), src)
                pc = U_(F_DIRI)
                gt2 = tguard.get(id(fg))
                if gt2 is not None and gt2['hp'] > 0: kena(gt2, max(1, round(mul(net(gt2)) * pc)), src)
                else: kena(fg, max(1, round(mul(net(fg)) * pc)), src)
                return
            d = mul(net(x))
            if id(x) in fself: d = max(1, round(d * U_(F_HINDAR)))
            kena(x, d, src)
        rnd.shuffle(acts)
        for u, sk, tg, ok in acts:
            if not ok: continue
            if INISIATIF and u['hp'] <= 0: continue      # (opsional) yang sudah jatuh oleh serangan sebelumnya tidak sempat menyerang
            r = u['r']
            if sk == 'basic':
                if id(tg) not in stealth and tg['hp'] > 0: serangan(tg, u['atk'] * U_(BASIC[r]), 1, u)
            elif r == 'assassin' and sk == 's1':
                if id(tg) not in stealth and tg['hp'] > 0: serangan(tg, u['atk'] * U_(ASN1), 1, u)
            elif sk == 'strike':
                if id(tg) not in stealth and tg['hp'] > 0: serangan(tg, u['atk'] * U_(STRIKE), 1, u)
            elif r == 'mage' and sk == 's1':
                for x in A(1 - u['t']):
                    if id(x) not in stealth: serangan(x, u['atk'] * U_((.65, 1.0)), 1, u)
            elif r == 'fighter' and sk == 's2':
                if id(tg) not in stealth and tg['hp'] > 0:
                    ms = [U_((.15, .30)) for _ in range(4)]
                    for m in ms: serangan(tg, u['atk'] * m, m / sum(ms), u)    # tiap pukulan lewat penjagaan sendiri-sendiri
                lain = [x for x in A(1 - u['t']) if x is not tg] or [tg]
                t2 = rnd.choice(lain)
                if id(t2) not in stealth and t2['hp'] > 0: serangan(t2, u['atk'] * U_((.45, .65)), 1, u)
        for u in U:
            for k in ('s1', 's2'):
                if u['cd'][k] > 0: u['cd'][k] -= 1
            if u['hp'] <= 0: u['siap'] = 0
    a0, a1 = len(A(0)), len(A(1))
    h0, h1 = sum(x['hp'] for x in A(0)), sum(x['hp'] for x in A(1))
    if a0 == 0 and a1 == 0: how, win = 'seri', -1
    elif a1 == 0: how, win = 'habis', 0
    elif a0 == 0: how, win = 'habis', 1
    elif a0 != a1: how, win = 'hidup', 0 if a0 > a1 else 1
    elif h0 != h1: how, win = 'hp', 0 if h0 > h1 else 1
    else: how, win = 'seri', -1
    per = {}
    for u in U:
        d = per.setdefault(u['r'], dict(dmg=0, kill=0, mati=0, tahan=0, hidup=0, n=0, pertama=0))
        d['dmg'] += u['dmg']; d['kill'] += u['kill']; d['n'] += 1
        d['mati'] += 1 if u['mati'] else 0
        d['hidup'] += 0 if u['mati'] else 1
        d['tahan'] += u['mati'] if u['mati'] else rounds
    for t, x in pertama.items(): per[x['r']]['pertama'] += 1
    return dict(rounds=rounds, win=win, how=how, per=per, n=len(roles), tank_mati=U[0]['mati'], v_mati=V['mati'] if V else None, pulih=sum(u['pulih'] for u in U))


def laporan(S, p, maks, n, fighter, gaya):
    R = [main_satu(S, p, i, maks, fighter, gaya) for i in range(n)]
    pct = lambda f: round(100 * sum(1 for r in R if f(r)) / n)
    roles = BASE[:1] + (['fighter'] if fighter else []) + BASE[1:]
    agg = {}
    for k in roles:
        d = dict(dmg=0, kill=0, mati=0, tahan=0, hidup=0, n=0, pertama=0)
        for r in R:
            for kk in d: d[kk] += r['per'][k][kk]
        agg[k] = d
    tdmg = sum(a['dmg'] for a in agg.values()) or 1
    tkill = sum(a['kill'] for a in agg.values()) or 1
    tpert = sum(a['pertama'] for a in agg.values()) or 1
    rep = {k: dict(damage=round(100 * a['dmg'] / tdmg), kill=round(100 * a['kill'] / tkill), pingsan=round(100 * a['mati'] / a['n']),
                   pertama=round(100 * a['pertama'] / tpert), tahan=round(a['tahan'] / a['n'], 1), selamat=round(100 * a['hidup'] / a['n']),
                   dmg_rata=round(a['dmg'] / a['n'])) for k, a in agg.items()}
    return dict(pulih=round(st.mean(r['pulih'] for r in R) / max(1, st.mean(r['rounds'] for r in R))),
                giliran=round(st.mean(r['rounds'] for r in R), 1),
                kiri=pct(lambda r: r['win'] == 0), kanan=pct(lambda r: r['win'] == 1),
                habis=pct(lambda r: r['how'] == 'habis'), habis20=pct(lambda r: r['how'] == 'habis' and r['rounds'] <= 20), hidup=pct(lambda r: r['how'] == 'hidup'),
                hp=pct(lambda r: r['how'] == 'hp'), seri=pct(lambda r: r['how'] == 'seri'), peran=rep)


def cetak_peran(rep):
    print('     %-9s %9s %9s %9s %12s %9s %9s' % ('peran', 'damage%', 'kill-akhir%', 'pingsan%', 'pingsan-pertama%', 'bertahan', 'selamat%'))
    for k, v in rep.items():
        print('     %-9s %9d %9d %9d %12d %9.1f %9d' % (k, v['damage'], v['kill'], v['pingsan'], v['pertama'], v['tahan'], v['selamat']))


def varian(S, hp=1.0, atk=1.0, df=1.0):
    import copy
    T = copy.deepcopy(S)
    for r in T:
        T[r]['hp'] = round(T[r]['hp'] * hp); T[r]['atk'] = round(T[r]['atk'] * atk); T[r]['df'] = round(T[r]['df'] * df)
    return T


def uji_pengeroyokan(n=1000):
    """Satu karakter lemah dikeroyok 2-4 lawan terkuat tiap giliran (5 vs 5, maks 12 giliran). Seberapa lama ia bertahan?"""
    cfgs = [('tanpa bantuan', dict(heal=False, tank=False, fighter=False)), ('+healer', dict(heal=True, tank=False, fighter=False)),
            ('+healer+tank', dict(heal=True, tank=True, fighter=False)), ('+healer+tank+fighter', dict(heal=True, tank=True, fighter=True))]
    for p in (1.0, 0.7):
        print('\n== Pengeroyokan (semua jawaban benar)' if p == 1.0 else '\n== Pengeroyokan (peluang benar 0.7)')
        print('   %-9s %2s | %-22s %s' % ('korban', 'n', 'bantuan', 'hidup >2 giliran | >4 | >8 | rata-rata giliran bertahan'))
        for role in ('assassin', 'mage', 'healer'):
            for k in (2, 3, 4):
                for name, c in cfgs:
                    R = [main_satu(STAT, p, i, 12, True, 'terarah', fk=dict(role=role, n=k, **c))['v_mati'] for i in range(n)]
                    sel = lambda t: round(100 * sum(1 for x in R if x is None or x > t) / n)
                    print('   %-9s %2d | %-22s %3d%% | %3d%% | %3d%% | %.1f' % (role, k, name, sel(2), sel(4), sel(8), st.mean([x or 13 for x in R])))


def uji_pendek(n=800):
    """Permainan dengan soal sedikit (N soal, pengulangan U kali => giliran maks N*(U+1)); peluang benar 0.7, 4 vs 4."""
    V = [('bawaan', STAT), ('HP x0.5', varian(STAT, hp=.5)), ('HP x0.4 Def x0.5', varian(STAT, hp=.4, df=.5))]
    print('\n== Soal sedikit (4 vs 4, p=0.7): % pertandingan berakhir karena  habis / selisih hidup / selisih HP')
    for N in (5, 8, 10):
        for ul in (1, 2):
            print('  N=%d soal, ulang %d => maks %d giliran' % (N, ul, N * (ul + 1)))
            for name, S in V:
                cells = []
                for gaya in ('terarah', 'acak'):
                    R = [main_satu(S, .7, i, N * (ul + 1), False, gaya) for i in range(n)]
                    pc = lambda h: round(100 * sum(1 for r in R if r['how'] == h) / n)
                    cells.append('%s %2d/%2d/%2d' % (gaya, pc('habis'), pc('hidup'), pc('hp')))
                print('    %-17s %s' % (name, ' | '.join(cells)))


if __name__ == '__main__':
    maks = int(sys.argv[1]) if len(sys.argv) > 1 else 30
    n = int(sys.argv[2]) if len(sys.argv) > 2 else 1500
    print('Stat bawaan:', {k: tuple(v.values()) for k, v in STAT.items()}, '(hp, atk, def, heal)')
    print('Giliran maksimum %d (= soal habis), %d simulasi per baris\n' % (maks, n))
    for fighter in (False, True):
        for gaya in ('acak', 'terarah'):
            print('== %s | pemain %s' % ('5 vs 5 (dengan Fighter)' if fighter else '4 vs 4', gaya))
            for p in (0.5, 0.7, 0.9):
                r = laporan(STAT, p, maks, n, fighter, gaya)
                print('  p=%.1f giliran %4.1f | menang Sky/Dark %2d%%/%2d%% | via habis %2d%% (di antaranya selesai <=20 giliran %2d%%) · selisih hidup %2d%% · selisih HP %2d%% · seri %d%%' %
                      (p, r['giliran'], r['kiri'], r['kanan'], r['habis'], r['habis20'], r['hidup'], r['hp'], r['seri']))
            print('   Per peran (p=0.7):')
            lap = laporan(STAT, 0.7, maks, n, fighter, gaya)
            cetak_peran(lap['peran'])
            print('     rata-rata HP pulih per giliran (kedua tim): %d' % lap['pulih'])
    print('== Uji Tank dikeroyok: seluruh tim lawan menyerang Tank terus, tanpa heal, jawaban selalu benar')
    for fighter in (False, True):
        R = [main_satu(STAT, 1.0, i, 12, fighter, 'fokus', noheal=True)['tank_mati'] for i in range(n)]
        mati = [r for r in R if r]
        print('  %s: Tank pingsan dalam 2 giliran %d%% · dalam 3 giliran %d%% · rata-rata pingsan di giliran %.1f' % (
              '5 vs 5' if fighter else '4 vs 4', round(100 * sum(1 for r in mati if r <= 2) / n),
              round(100 * sum(1 for r in mati if r <= 3) / n), st.mean(mati) if mati else 0))
    uji_pengeroyokan(n)
    uji_pendek(n)
