# Arabic Encoding Fixer - Changelog

## v2.2
* **Fix**: Expanded supported database column types to include `varchar` and `tinytext`.
* **Improvement**: Enabled fixing of titles, names, and short text fields across all supported tables, including:
    * `banner` (name)
    * `link` (name)
    * `visits` (name)
    * `news` (name)
    * `f_cat` (name)
    * `forum` (name)
    * `directory` (all text fields)
    * `cat_dir` (name)

## v2.1
* **Initial Release**: Basic support for `text`, `mediumtext`, and `longtext` fields.
* **Feature**: Integrated `ArabicStringRepair` logic for mojibake recovery.
* **UI**: Added Admin dashboard interface for scanning and applying fixes.
