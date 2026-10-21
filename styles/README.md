# vBulletin 3.x Styles

A collection of styles and skins for vBulletin 3.x forums.

Many forums had their own skins, built by the community or by the admins themselves, and most of them are gone now. This folder is a place to keep whatever is left. If you still have an old style on a hard drive, a backup, or a forgotten server, please open a pull request and add it here.

## Structure

Group styles by the vBulletin version they were built for:

```
styles/
└── v3.8.x/
    └── style-name/
        ├── vbulletin-style.xml   # Style exported from the AdminCP
        ├── images/               # Image folder used by the style (if any)
        ├── screenshot.png        # Preview (optional, but nice to have)
        └── README.md             # A few lines about the style
```

Use `v3.0.x`, `v3.5.x`, `v3.6.x`, `v3.7.x` or `v3.8.x` for the version folder. If you're not sure which version a style is for, put it in `unknown/`.

## How to Export a Style

1. Go to **AdminCP → Styles & Templates → Download / Upload Styles**.
2. Under **Download**, select the style and click **Download**. You get a `vbulletin-style.xml` file.
3. Copy the style's image folder (usually something like `images/your-style/`) from the server.

To install a style, use **Upload** on the same page, then point the style's image path to wherever you put the images.

## Contributing

1. Fork this repo.
2. Add your style following the structure above.
3. In the style's `README.md`, include what you know about it:
   - Style name and original author (if known)
   - vBulletin version it was made for
   - The forum it was used on, and any story behind it
4. Open a pull request.

Incomplete styles are welcome too, such as just the XML or just the images. Something is better than nothing.

## Credits

Please keep the original author's credit whenever you know it. These styles were made by people who loved their forums, and their names deserve to be remembered along with the work.
