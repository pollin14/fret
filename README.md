# FRET Calculator

## Install

Only install PHP

```bash
sudo apt install php
```

## How to use

First put your data files in the chapters `data/cfp` and `data/yfp`. Now run

To run CFP

```bash 
./fret cfp
```

To run CFP

```bash 
./fret yfp
```

Both commands accept the following options

| Option      | Default | Description |
| ----------- | ----------- |---------|
| `--number-of-files`      | 29       |Number of file to process|
| `--number-of-lines-by-file`   | 512        |Number of lines in each file (the header does not count)|

Example:

```bash
./fret cfp --number-of-files=32 --number-of-lines-by-file=512
```

To show the help running

```bash
./fret cfp --help
```


