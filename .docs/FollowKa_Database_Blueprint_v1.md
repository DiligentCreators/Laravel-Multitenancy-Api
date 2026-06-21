# FollowKa Database Blueprint v1

## Overview

FollowKa is a multi-tenant industry CRM platform built on:

-   Laravel 13
-   Inertia.js
-   Vue.js
-   Shared Database Architecture
-   Tenant Isolation via `tenant_id`
-   Core CRM + Installable Industry Modules

------------------------------------------------------------------------

# Core Principles

## Multi-Tenant

All business records belong to a tenant.

``` text
tenant_id
```

## Ownership

Most records support:

``` text
owner_id
created_by
updated_by
```

## Team Visibility

``` text
team_id
```

## Soft Deletes

All critical business tables should support soft deletes.

------------------------------------------------------------------------

# Core Tables

## tenants

  Column          Type
  --------------- -----------
  id              bigint
  uuid            uuid
  name            string
  slug            string
  status          enum
  trial_ends_at   timestamp
  suspended_at    timestamp
  archived_at     timestamp
  deleted_at      timestamp
  created_at      timestamp
  updated_at      timestamp

## tenant_domains

  Column        Type
  ------------- -----------
  id            bigint
  tenant_id     fk
  domain        string
  is_primary    boolean
  verified_at   timestamp

------------------------------------------------------------------------

# Users & Security

## users

  Column          Type
  --------------- -----------
  id              bigint
  tenant_id       fk
  first_name      string
  last_name       string
  email           string
  phone           string
  password        string
  status          enum
  last_login_at   timestamp

## teams

## team_user

## roles

## permissions

## model_has_roles

## model_has_permissions

(Spatie Permission)

------------------------------------------------------------------------

# Subscription & Billing

## plans

## features

## plan_features

## subscriptions

## subscription_items

## invoices

## payments

## coupons

## usage_counters

Track:

-   Users
-   Contacts
-   Storage
-   Module Usage

------------------------------------------------------------------------

# CRM Core

## people

Universal contact entity.

Fields:

-   first_name
-   last_name
-   email
-   phone
-   whatsapp
-   date_of_birth

## organizations

Fields:

-   name
-   website
-   email
-   phone
-   industry

## contacts

Links people and organizations.

## leads

Fields:

-   tenant_id
-   person_id
-   organization_id
-   pipeline_id
-   stage_id
-   source
-   value
-   owner_id
-   status

## pipelines

## pipeline_stages

## tasks

## notes

## activities

## comments

## tags

## taggables

------------------------------------------------------------------------

# Custom Fields

## custom_fields

## custom_field_values

Supports:

-   CRM
-   Solar
-   Agency
-   Real Estate

------------------------------------------------------------------------

# Documents

## folders

## documents

Stored in Wasabi.

------------------------------------------------------------------------

# Notifications

## notifications

## notification_templates

Channels:

-   Email
-   SMS
-   Push
-   In-App

------------------------------------------------------------------------

# Forms

## forms

## form_fields

## form_submissions

------------------------------------------------------------------------

# Automation

## workflows

## workflow_steps

## workflow_runs

## workflow_logs

------------------------------------------------------------------------

# API & Integrations

## api_tokens

## webhooks

## webhook_logs

------------------------------------------------------------------------

# Activity & Audit

## activity_logs

(Spatie Activity Log)

## audits

(OwenIt Auditing)

------------------------------------------------------------------------

# Solar Module

## solar_leads

## site_surveys

## roof_measurements

## solar_proposals

## solar_system_designs

## installations

## installation_teams

## contracts

## financing_records

## maintenance_records

## warranty_claims

------------------------------------------------------------------------

# Agency Module

## clients

## projects

## campaigns

## retainers

## timesheets

## deliverables

## seo_reports

## social_media_plans

## content_calendars

------------------------------------------------------------------------

# Real Estate Module

## properties

## property_owners

## buyers

## real_estate_tenants

## agents

## listings

## property_visits

## offers

## property_contracts

## commissions

## rentals

## maintenance_requests

------------------------------------------------------------------------

# Client Portal (Future)

## client_users

## client_access

## quotations

## approvals

## support_tickets

## chats

------------------------------------------------------------------------

# Global Index Strategy

Index all:

``` text
tenant_id
owner_id
team_id
status
created_at
```

Composite Examples:

``` text
tenant_id + status
tenant_id + owner_id
tenant_id + team_id
```

------------------------------------------------------------------------

# Lifecycle

Day 0: Subscription Expired

Day 3: Suspended

Day 30: Archived

Day 90: Permanent Delete

------------------------------------------------------------------------

# Recommended Packages

-   spatie/laravel-permission
-   spatie/laravel-activitylog
-   spatie/laravel-medialibrary
-   spatie/laravel-settings
-   spatie/laravel-query-builder
-   spatie/laravel-data
-   maatwebsite/excel
-   laravel/scout
-   meilisearch
-   owen-it/laravel-auditing
-   laravel/cashier-stripe
-   laravel/sanctum
-   laravel/horizon

------------------------------------------------------------------------

# Technology Stack

-   Laravel 13
-   PHP 8.4
-   Vue 3
-   Inertia.js
-   MySQL 8.4
-   Redis
-   Horizon
-   Meilisearch
-   Wasabi Storage
-   Nginx
