# Tabel damage & heal (stat bawaan)

Stat: Tank HP 360 / Atk 24 / Def 25, Fighter HP 285 / Atk 56 / Def 18, Assassin HP 158 / Atk 80 / Def 8, Mage HP 176 / Atk 65 / Def 10, Healer HP 190 / Atk 60 / Def 12

Damage = max(1, round(Attack x persen - Defend target)). Semua target berHP penuh dan tanpa perlindungan (Benteng, Pasang Badan, Lompat Pelindung). Tank yang menjaga teman menerima damage persis seperti baris "Tank" (memakai Defend tank). Mage Kutukan, Benteng Tim, Pasang Badan, Lompat Pelindung tidak menimbulkan damage langsung.

### Tank - Serangan dasar (10%-20% x Attack 24)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 1-1 (rata 1) | 359-359 | 359 | 360.0 |
| Fighter | 285 | 18 | 1-1 (rata 1) | 284-284 | 284 | 285.0 |
| Assassin | 158 | 8 | 1-1 (rata 1) | 157-157 | 157 | 158.0 |
| Mage | 176 | 10 | 1-1 (rata 1) | 175-175 | 175 | 176.0 |
| Healer | 190 | 12 | 1-1 (rata 1) | 189-189 | 189 | 190.0 |

### Fighter - Serangan dasar (15%-35% x Attack 56)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 1-1 (rata 1) | 359-359 | 359 | 360.0 |
| Fighter | 285 | 18 | 1-2 (rata 1) | 283-284 | 284 | 285.0 |
| Assassin | 158 | 8 | 1-12 (rata 6) | 146-157 | 152 | 26.3 |
| Mage | 176 | 10 | 1-10 (rata 4) | 166-175 | 172 | 44.0 |
| Healer | 190 | 12 | 1-8 (rata 2) | 182-189 | 188 | 95.0 |

### Fighter - Rentetan Pukulan - pukulan ke-5 (lawan kedua) (45%-65% x Attack 56)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 1-11 (rata 6) | 349-359 | 354 | 60.0 |
| Fighter | 285 | 18 | 7-18 (rata 13) | 267-278 | 272 | 21.9 |
| Assassin | 158 | 8 | 17-28 (rata 23) | 130-141 | 135 | 6.9 |
| Mage | 176 | 10 | 15-26 (rata 21) | 150-161 | 155 | 8.4 |
| Healer | 190 | 12 | 13-24 (rata 19) | 166-177 | 171 | 10.0 |

### Assassin - Serangan dasar (20%-50% x Attack 80)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 1-15 (rata 3) | 345-359 | 357 | 120.0 |
| Fighter | 285 | 18 | 1-22 (rata 10) | 263-284 | 275 | 28.5 |
| Assassin | 158 | 8 | 8-32 (rata 20) | 126-150 | 138 | 7.9 |
| Mage | 176 | 10 | 6-30 (rata 18) | 146-170 | 158 | 9.8 |
| Healer | 190 | 12 | 4-28 (rata 16) | 162-186 | 174 | 11.9 |

### Assassin - Tusukan Mematikan (skill 1) (90%-140% x Attack 80)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 47-87 (rata 67) | 273-313 | 293 | 5.4 |
| Fighter | 285 | 18 | 54-94 (rata 74) | 191-231 | 211 | 3.9 |
| Assassin | 158 | 8 | 64-104 (rata 84) | 54-94 | 74 | 1.9 |
| Mage | 176 | 10 | 62-102 (rata 82) | 74-114 | 94 | 2.1 |
| Healer | 190 | 12 | 60-100 (rata 80) | 90-130 | 110 | 2.4 |

### Assassin - Serangan Bayangan (skill 2, giliran ke-2) (230%-280% x Attack 80)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 159-199 (rata 179) | 161-201 | 181 | 2.0 |
| Fighter | 285 | 18 | 166-206 (rata 186) | 79-119 | 99 | 1.5 |
| Assassin | 158 | 8 | 176-216 (rata 196) | 0-0 (KO) | 0 | 1 (KO langsung) |
| Mage | 176 | 10 | 174-214 (rata 194) | 0-2 (KO) | 0 | 1 (KO langsung) |
| Healer | 190 | 12 | 172-212 (rata 192) | 0-18 (KO) | 0 | 1 (KO langsung) |

### Mage - Serangan dasar (10%-30% x Attack 65)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 1-1 (rata 1) | 359-359 | 359 | 360.0 |
| Fighter | 285 | 18 | 1-2 (rata 1) | 283-284 | 284 | 285.0 |
| Assassin | 158 | 8 | 1-12 (rata 5) | 146-157 | 153 | 31.6 |
| Mage | 176 | 10 | 1-10 (rata 3) | 166-175 | 173 | 58.7 |
| Healer | 190 | 12 | 1-8 (rata 1) | 182-189 | 189 | 190.0 |

### Mage - Hujan Meteor / Badai Es (tiap lawan) (65%-100% x Attack 65)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 17-40 (rata 29) | 320-343 | 331 | 12.4 |
| Fighter | 285 | 18 | 24-47 (rata 36) | 238-261 | 249 | 7.9 |
| Assassin | 158 | 8 | 34-57 (rata 46) | 101-124 | 112 | 3.4 |
| Mage | 176 | 10 | 32-55 (rata 44) | 121-144 | 132 | 4.0 |
| Healer | 190 | 12 | 30-53 (rata 42) | 137-160 | 148 | 4.5 |

### Healer - Serangan dasar (5%-25% x Attack 60)

| Target | HP | Def | Damage masuk (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata | Kali serang sampai KO (rata-rata) |
|---|---|---|---|---|---|---|
| Tank | 360 | 25 | 1-1 (rata 1) | 359-359 | 359 | 360.0 |
| Fighter | 285 | 18 | 1-1 (rata 1) | 284-284 | 284 | 285.0 |
| Assassin | 158 | 8 | 1-7 (rata 1) | 151-157 | 157 | 158.0 |
| Mage | 176 | 10 | 1-5 (rata 1) | 171-175 | 175 | 176.0 |
| Healer | 190 | 12 | 1-3 (rata 1) | 187-189 | 189 | 190.0 |

### Fighter - Rentetan Pukulan, 4 pukulan pertama ke 1 lawan (tiap 15%-30%, total 60%-120% x Attack 56, Defend dipotong sekali)

| Target | HP | Def | Total 4 pukulan (min-maks, rata-rata) | Sisa HP (min-maks) | Sisa HP rata-rata |
|---|---|---|---|---|---|
| Tank | 360 | 25 | 9-42 (rata 25) | 318-351 | 335 |
| Fighter | 285 | 18 | 16-49 (rata 32) | 236-269 | 253 |
| Assassin | 158 | 8 | 26-59 (rata 42) | 99-132 | 116 |
| Mage | 176 | 10 | 24-57 (rata 40) | 119-152 | 136 |
| Healer | 190 | 12 | 22-55 (rata 38) | 135-168 | 152 |

### Healer - pemulihan ke teman (atau diri sendiri), kekuatan = Attack Healer 60

Pemulihan berhenti di HP maksimal. Kolom terakhir: HP setelah di-heal bila teman sedang sekarat dengan HP 20.

| Skill | Persen | Jumlah pulih (min-maks, rata-rata) |
|---|---|---|
| Penyembuhan (skill 1, 1 teman) | 200%-250% | 120-150 (rata 135) |
| Hujan Cahaya (skill 2, tiap teman) | 80%-100% | 48-60 (rata 54) |

| Teman | HP maks | Penyembuhan: % HP maks | HP setelah heal dari HP 20 | Hujan Cahaya: % HP maks | HP setelah heal dari HP 20 |
|---|---|---|---|---|---|
| Tank | 360 | 33%-42% | 140-170 | 13%-17% | 68-80 |
| Fighter | 285 | 42%-53% | 140-170 | 17%-21% | 68-80 |
| Assassin | 158 | 76%-95% | 140-158 | 30%-38% | 68-80 |
| Mage | 176 | 68%-85% | 140-170 | 27%-34% | 68-80 |
| Healer | 190 | 63%-79% | 140-170 | 25%-32% | 68-80 |

