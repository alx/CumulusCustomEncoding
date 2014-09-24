CumulusCustomEncoding
=====================

Custom encoding script for CumulusClips that includes :

* ```escapeshellarg``` call to avoid issues with spaces in filenames
* a watermarking step
* a webvtt step to include preview thumbnails supported in newest version of jwplayer

## Installation

```
cd cc-content/plugins/
git clone https://github.com/alx/CumulusCustomEncoding.git
cd ../../cc-core/system/
mv encode.php encode.php.origin
ln -sf ../../cc-content/plugins/CumulusCustomEncoding/encode.php
```

New encoded videos will now use the customized version of encoding process.
