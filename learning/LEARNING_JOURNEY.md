# Laravel 13 for NestJS Developers — Learning Journey

> A conceptual-bridging guide for backend devs transitioning from NestJS (TypeScript) to Laravel 13, built around a To-Do API.
>
> **Core mental model:** NestJS is *explicit & declarative* (decorators everywhere). Laravel is *convention-driven & fluent*. That contrast explains most of the "where is the equivalent of X?" friction.

## Phases

| # | Phase | Focus |
|---|---|---|
| 1 | [Lifecycle & Routing](phase-1-lifecycle-routing.md) | Bootstrap, Service Providers, route files, controllers |
| 2 | [Data Layer & Validation](phase-2-data-layer-validation.md) | Migrations, Eloquent, Form Requests, API Resources |
| 3 | [Business Logic & DI](phase-3-business-logic-di.md) | Service classes, the Service Container, autowiring |
| 4 | Security & Testing *(not written yet)* | Middleware, Sanctum auth, Pest/PHPUnit |

Each phase file ends with a **Developer Log & Reference Notes** section: a NestJS↔Laravel syntax table, "Gotchas for TypeScript Devs", and a quick-reference command summary.
