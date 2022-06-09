# OpenBenches Phrases

This is a very simplistic attempt at generating a list of common phrases from inscriptions within data from [OpenBenches](https://openbenches.org/).
If you just want to view the data you can find CSV files in the `output/` directory.

This was created as a fun afternoon task after [coming across this issue](https://github.com/openbenches/openbenches.org/issues/216).

### Running

All logic is in the `generate.php` file. This script uses a lot of memory. You can run from the command line, with a set memory limit of 1GB, like so:

```bash
php -d memory_limit=1G generate.php
```

On first run the script will look-up to the `https://openbenches.org` API fetch the latest data. The fetched inscriptions will be cached for future runs to prevent abusing the OpenBenches API.
You can simply delete the `cache/inscriptions.json` file to clear the cache.

You can find a set of `const` values at the top of the `generate.php` script to allow tweaking the parameters used for identifying phrases.

This script was built and tested using PHP 8.0.13.

### Limitations

- This script is not currently built to be memory or CPU efficient, it will use about 800MB of memory. Therefore, it's not really suitable for running server-side.
  - The internal phrase map this builds contains a lot of word duplication. A big improvement to memory usage would likely be using some integer-based look-up maps to words instead of passing/using strings everywhere.   
- The locating and filtering of phrases is relatively dumb and is primarily driven by (frequency * length).

### License

The code in this repository is license under the MIT license.
The data used, and subsequently the results in the `output/` directory, can be considered under the [Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)](https://creativecommons.org/licenses/by-sa/4.0/) as per the original OpenBenches data license.