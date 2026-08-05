# Pediment Child Theme — retired

> **This repository is archived and no longer maintained.** The parent/child theme model it
> documents was retired in Pediment v3.0.0 (2026-07-30). Nothing here is installed on a supported
> Pediment site.

## What replaced it

Pediment is now a **single WordPress plugin** paired with a **standalone client theme**. There is no
parent theme, so there is nothing to be a child of. A client theme owns its own brand and content
and depends on the plugin only through the blocks, templates and tokens the plugin provides.

Client themes are no longer created by forking a template repository by hand. They are scaffolded:

```text
/plugin marketplace add Bergert-Digital/pediment
/plugin install pediment@pediment
```

Then, in an empty directory for the new client site:

```text
/pediment:start
```

## Where the documentation moved

Everything that used to live here is in the [Pediment monorepo](https://github.com/Bergert-Digital/pediment):

| Topic | Now at |
| --- | --- |
| Building and maintaining a client site | [`docs/client-sites.md`](https://github.com/Bergert-Digital/pediment/blob/main/docs/client-sites.md) |
| Block authoring contract | [`docs/blocks.md`](https://github.com/Bergert-Digital/pediment/blob/main/docs/blocks.md) |
| Declarative seeding | [`docs/seeding.md`](https://github.com/Bergert-Digital/pediment/blob/main/docs/seeding.md) |
| Quality bars | [`docs/STANDARDS.md`](https://github.com/Bergert-Digital/pediment/blob/main/docs/STANDARDS.md) |
| The client theme starting point | [`client-template/`](https://github.com/Bergert-Digital/pediment/tree/main/client-template) |

## If you run a site built from this template

It still works — nothing was removed from your server. Migrating it to the current architecture
means converting the child theme into a standalone theme and pairing it with the Pediment plugin.
[`docs/client-sites.md`](https://github.com/Bergert-Digital/pediment/blob/main/docs/client-sites.md)
describes the target shape.

The code in this repository remains under its original licence and stays readable for reference.
