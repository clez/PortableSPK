PortableSPK
===========

Synology SPK repository PHP class

See examples for a quick start.

Features:
- Synology Package source
    - autoconfiguration by INFO parsing and mapping (= no administration)
    - language aware: returns dname_<lng> and description_<lng> from INFO if available, default="enu"
    - archirecture aware: returns only packages running ("arch" eqauls or "noarch")
    - enforce minimal firmware build version
    - WIZARDUI detection: sets "qinst" automatically to let wizards appear
    - INFO icon(base64) > PACKAGE_ICON.PNG
    - compressed and uncompressed .spk
    - filter old package versions
    - ?debug mode
    - optional SPK INFO cache
    - optional 4.2 screenshots: place jpeg images named <packageid>_0.jpg in ./pkg_img/
    - optional 4.2 category hinting: place a fitting "category=" in INFO for passthru
    - stackable, allows multiple instances
    - iterable (foreach() packages)
    - JSON __toString()
                -


Environment:
- Apache2/PHP5
- No Database
- No external executables
- No package administration, drop in spk packages, SPK index monitors its folder's mtime
  and updates automatically
