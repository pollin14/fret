# FRET Calculator

## Install

Only install PHP 7.4

```bash
sudo apt install php php-mbstring
```

Check if php is correctly installed with

```bash
php -v
```

The result must be similar to

```bash
PHP 7.4.3 (cli) (built: Oct  6 2020 15:47:56) ( NTS )
Copyright (c) The PHP Group
Zend Engine v3.4.0, Copyright (c) Zend Technologies
    with Zend OPcache v7.4.3, Copyright (c), by Zend Technologies
    with Xdebug v2.9.2, Copyright (c) 2002-2020, by Derick Rethans
```

 Download the `fret` file from the releases section [https://github.com/pollin14/fret/releases](Release).

## How to use


Firstly, put your data files in the chapters `data/cfp` and `data/yfp` aside `fret`. Then you can run CFP with

```bash 
php fret cfp
```

or YFP with

```bash 
php fret yfp
```


Both commands accept the following options

| Option      | Default | Description |
| ----------- | ----------- |---------|
| `--number-of-files`      | 29       |Number of file to process|
| `--number-of-lines-by-file`   | 512        |Number of lines in each file (the header does not count)|

Example:

```bash
php fret cfp --number-of-files=32 --number-of-lines-by-file=512
```

To show the help running

```bash
php fret cfp --help
```

## Creating a release

In order to create a new release run the following command

```
php fret app:build --build-version=released 
```

the release will be in `builds/fret`

