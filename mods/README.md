# vBulletin 3.x Mods

A collection of modifications (mods, hacks, plugins, products) for vBulletin 3.x.

Back in the day, vBulletin.org and countless community sites shared thousands of mods: arcades, shoutboxes, thank-you buttons, award systems, custom BB codes, and many small tweaks that made each forum unique. Most of those downloads are long gone. If you still have some lying around, please open a pull request and add them here.

## Structure

Group mods by the vBulletin version they were built for:

```
mods/
└── v3.8.x/
    └── mod-name/
        ├── product-mod-name.xml   # Product file to import via the AdminCP (if any)
        ├── upload/                # Files to copy into the forum root, same layout as vBulletin
        ├── file-edits.txt         # Manual template/PHP edits (for older hacks)
        ├── screenshot.png         # Preview (optional)
        └── README.md              # About the mod and how to install it
```

Use `v3.0.x`, `v3.5.x`, `v3.6.x`, `v3.7.x` or `v3.8.x` for the version folder. If a mod works on several versions, put it under the oldest one and list the others in its README. If you're not sure, use `unknown/`.

Not every mod has all of these files. Plugin-based mods (3.5 and later) are often just a product XML. Older 3.0.x hacks usually rely on manual file and template edits. Include whatever the mod came with.

## Mod README

Each mod folder should have a short `README.md` covering what you know:

- **Name and version** of the mod
- **Original author** and where it was released (e.g. a vBulletin.org thread), if known
- **vBulletin versions** it was made for or tested on
- **What it does**, in a sentence or two
- **Installation steps**: product import, files to upload, template or file edits
- **Uninstall steps**, if they're not just removing the product
- Known issues, and the forum it was used on if you'd like to share that story

If the original download came with an install guide, include it as is. It's part of the history too.

## How to Export a Product

For mods installed as products (vBulletin 3.5 and later):

1. Go to **AdminCP → Plugins & Products → Manage Products**.
2. Find the product, open its dropdown and select **Export**.
3. Save the downloaded `product-*.xml` file, then copy the mod's PHP files, images and other assets from your server into `upload/`.

## Contributing

1. Fork this repo.
2. Add your mod following the structure above.
3. Write the mod's `README.md`.
4. Open a pull request, with one mod per PR if possible.

Incomplete mods are welcome too, such as a product XML with no files or a set of files with no instructions. Note what's missing in the README so others can help fill the gaps.

## Notes

- **Credit the original author** whenever you know it. Don't remove copyright notices from the files.
- **Remove anything private** before submitting: database credentials, license keys, API keys, or your forum's user data.
- **Expect these mods to be insecure.** Many were written before modern security practices and haven't been maintained for years. They're kept here for history and reference, not for use on a live site.
