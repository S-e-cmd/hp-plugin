# garden-opening-status v3.2.24

Source package: `garden-opening-status-v3.2.24-public-output-fix.zip`

SHA-256:

```text
d0753924c9bd576170f98a63a44755d183cd55466c482de73c69ec7b3aa3cc66
```

Expected size: `43291` bytes

## Restore

```bash
cat garden-opening-status-v3.2.24.zip.b64.part00 \
    garden-opening-status-v3.2.24.zip.b64.part01 \
  | tr -d '\n\r ' \
  | base64 --decode \
  > garden-opening-status-v3.2.24-public-output-fix.zip

sha256sum garden-opening-status-v3.2.24-public-output-fix.zip
unzip -t garden-opening-status-v3.2.24-public-output-fix.zip
```

The checksum must match the SHA-256 above before use.
