# Test fixtures

Ground truth for each folder is in its `expected.json`.

## `pdfs/`: 10 open-access articles

All are published under **Creative Commons Attribution 4.0 (CC BY 4.0)**. They are redistributed here unchanged for testing, except file 10 (see below). The topic is SMEs and technology adoption, matching the spec's worked example, so the set also works as a sample project for clustering and synthesis.

| File | Article | Licence / source |
| --- | --- | --- |
| 01 | Fan M, Qalati SA, Khan MAS, Shah SMM, Ramzan M, Khan RS (2021). Effects of entrepreneurial orientation on social media adoption and SME performance: The moderating role of innovation capabilities. *PLOS ONE* 16(4): e0247320. https://doi.org/10.1371/journal.pone.0247320 | CC BY 4.0, journals.plos.org |
| 02 | Bochner R (2026). Digital financial inclusion and SME performance: a structural equation modeling analysis of FinTech adoption impact in emerging markets. *Future Business Journal* 12:317. https://doi.org/10.1186/s43093-026-01024-z | CC BY 4.0, fbj.springeropen.com |
| 03 | Gallant NDD, Amadi-Echendu AP, Esterhuyzen E (2025). Exploring SME owners' motivation to learn and acquire new skills for the adoption of e-commerce. *Southern African Journal of Entrepreneurship and Small Business Management* 17(1). https://doi.org/10.4102/sajesbm.v17i1.900 | CC BY 4.0, sajesbm.co.za |
| 04 | Mohd Rasdi R, Umar Baki N (2025). Navigating the AI landscape in SMEs: Overcoming internal challenges and external obstacles for effective integration. *PLOS ONE* 20(5): e0323249. https://doi.org/10.1371/journal.pone.0323249 | CC BY 4.0, journals.plos.org |
| 05 | Abraha TG, Gebre HT (2025). Assessment of performance and challenges of small and micro enterprises: Fireweyni town, Ethiopia. *PLOS ONE* 20(4): e0320681. https://doi.org/10.1371/journal.pone.0320681 | CC BY 4.0, journals.plos.org |
| 06 | Ben Hamadi Z, Karjalainen H (2024). Identités managériales : le cas des dirigeants de PME tunisiens. *Revue Interdisciplinaire Droit et Organisations* 7. https://doi.org/10.34699/rido.2024.37 (HAL: hal-04624721) | CC BY 4.0, hal.science |
| 07 | Pillet C (1987). Nouvelles technologies et PME industrielles : une enquête en Provence - Alpes - Côte d'Azur. *Courrier des Statistiques* 41, pp. 47–49. (HAL: hal-05430423) | CC BY 4.0, insee.hal.science |
| 08 | Martínez J, Romo L, Riascos S (2024). Avances en la transformación digital de las MiPymes impulsadas por la pandemia COVID-19. *Journal of Technology Management & Innovation* 19(1). https://doi.org/10.4067/S0718-27242024000100052 | CC BY 4.0, jotmi.org |
| 09 | Ramos Rodriguez CV, Guerrero Velástegui CA (2022). Marketing digital y economía de empresa: un estudio de las Mipymes de la provincia de Tungurahua frente al fenómeno Covid-19. *Revista Eruditus* 3(3). https://doi.org/10.35290/re.v3n3.2022.714 | CC BY 4.0, revista.uisrael.edu.ec |
| 10 | Durrani N, Raziq A, Mahmood T, Khan MR (2024). Barriers to adaptation of environmental sustainability in SMEs: A qualitative study. *PLOS ONE* 19(2): e0298580. https://doi.org/10.1371/journal.pone.0298580 | CC BY 4.0, journals.plos.org. **Changed:** only pages 1–4, rendered as greyscale images (150 DPI) with no text layer, to simulate a scanned document. |

There are 3 languages (6 English, 2 French, 2 Spanish) and a mix of designs (5 surveys, 3 qualitative studies, 2 mixed-methods studies). File 10 is scanned.

## `web/`: 5 saved pages

Original synthetic pages written for Skimr's tests: a news article, a policy report, a blog post, a paywalled teaser and a JavaScript-only app shell. The site names and people are fictional, and URLs use `example.org`. Each page includes typical clutter (menus, a cookie banner, a footer, comments) that extraction must remove.

## `images/`: 3 photo sets (to do: P3 takes the photos)

Real phone photos are needed for OCR accuracy (B5 target: at least 90% word accuracy in good light). See `images/README.md` for what to photograph.
