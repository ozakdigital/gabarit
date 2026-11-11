<?php

declare(strict_types=1);

namespace Gabarit\Core\Model;

enum Frontend: string
{
    case Twig = 'twig';
    case Blade = 'blade';
    case Vue = 'vue';
    case React = 'react';
    case Svelte = 'svelte';
    case Angular = 'angular';
    case Solid = 'solid';
    case Preact = 'preact';
    case InertiaVue = 'inertia-vue';
    case None = 'none';
}
