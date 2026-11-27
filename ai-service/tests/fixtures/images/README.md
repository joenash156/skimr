# Photo sets for OCR tests (P3: please take these)

1. Print [`to-photograph.pdf`](to-photograph.pdf) (3 pages: English, French, Spanish). Use paper rather than a screen: photographing a screen causes moiré patterns.
2. Take **3 sets** with your phone, one photo per sheet, and save them as JPEG:

| Folder | How to take them |
| --- | --- |
| `good-light/` | Daylight or a bright room. Hold the phone flat above the page, fill the frame, keep it in focus. |
| `poor-light/` | A dim room in the evening, no flash, with a shadow across part of the page. |
| `tilted/` | Good light, phone at about 20–30° to the page and turned a little. Take one of the three in **landscape**, so the rotation comes from EXIF. |

3. Name the files `sheet-1.jpg`, `sheet-2.jpg`, `sheet-3.jpg` in each folder. Keep each under **5 MB** (spec 6.6); use your phone's "medium" size if needed.
4. **Privacy:** turn **off** your camera's location setting before taking them. Phone photos store GPS coordinates in EXIF, and these files go into a shared repo. (The EXIF *orientation* tag must stay, because the tilted set tests it.) If you forget, ask the assistant to remove the GPS data before you commit.
5. **Before committing**, check that no faces, people or private papers are visible. Then change that set's `"status"` in `expected.json` from `"pending"` to `"ready"`.

The expected text of each sheet is already in `expected.json`, taken from the source PDFs.
