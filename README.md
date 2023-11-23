## Table of contents

* Introduction
* Overview
* Requirements
* Installation
* Configuration
* Maintainers


## Introduction

The [Kontainer](https://kontainer.com) module is a robust solution designed to
enhance your Drupal website's media management capabilities. Empower your site
with seamless media imports, efficient CDN integration, and customizable
formatting templates. Kontainer improves the way you handle digital assets,
providing a streamlined and user-friendly experience.

For additional details on the use cases of the Kontainer module and instructions
on its setup, please reach out to us on our website
[https://kontainer.com](https://kontainer.com).

## Overview

The Kontainer module enhances Drupal's media capabilities with key features:

**Media Import:**
  - Seamlessly import media into Drupal's media storage.

**CDN Integration:**
  - Directly import assets via Kontainer's content delivery network (CDN).

**Crop and Resize Templates:**
  - Define and apply customizable crop and resize templates for CDN-imported
media.

**File Usage Tracking:**
  - Monitor file usage in Kontainer, allowing you to track how often and exactly
where your files are being used.

***Note:***

- For file usage tracking, Kontainer files are tracked only when used on node
entites. By media storage, tracking is done for everything that is configured
in the entity_usage module configuration (entity embed, entity reference,
linkit...), but only if referenced directly on a node, nesting is tracked only
for paragraphs. For CDN fields only fields directly in the node and fields in
nested paragraphs are tracked.

## Requirements

This module requires the following modules:
* Field (https://www.drupal.org/docs/8/core/modules/field)
* File (https://www.drupal.org/docs/8/core/modules/file)
* Image (https://www.drupal.org/docs/core-modules-and-themes/core-modules/image-module)
* Media (https://www.drupal.org/docs/8/core/modules/media)
* Entity Usage (https://www.drupal.org/project/entity_usage)

## Instalation

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/extending-drupal/installing-modules for further
information.

## Configuration

    1. Navigate to Administration->Configuration->Media->Kontainer
       (/admin/config/media/kontainer). Enter your Kontainer URL (without any
       trailing /) and select the preferred media source.
***Note:***
When adding new a new
field to an entity, only the Kontainer field type of the selected media source
is available. This setting can be toggled without any data loss or any
limitation of usage of the field types on entities.

    2.  The CDN media source also allows you to configure CDN image conversions
        on /admin/structure/cdn-image-conversion.
***Note:***
The CDN media source
also allows you to configure CDN image conversions on
/admin/structure/cdn-image-conversion. If an image conversion gets deleted, all
the view displays where it is being used are set to the default (original image)
option.

    3. Configure Kontainer, Entity usage and Media module permissions on
       /admin/people/permissions/module/kontainer%2Cmedia%2Centity_usage

    4. In your Kontainer, insert this URL to create a Drupal integration:
       https://{Drupal site base url}/kontainer/api/file-usages. Then enter the
       integration ID and the integration secret from Kontainer to the
       corresponding fields on /admin/config/media/kontainer. Save the
       configuration

    5. Go to /admin/config/entity-usage/settings and enable the "Kontainer
       Entity Reference" tracking plugin (if not yet enabled) and configure
       other Entity Usage module settings as you like. Save the configuration.

    6. Export the configuration (drush cex).

## Maintainers

Supporting organizations:
* Kontainer A/S - https://kontainer.com
* Agiledrop - https://www.drupal.org/agiledrop

Current maintainers:
* Domen Slogar - https://www.drupal.org/u/slogar32
