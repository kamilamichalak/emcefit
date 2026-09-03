<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="eMCeFit — studio fitness" />

    <main class="emcefit-hero">
        <div class="emcefit-hero__inner">
            <div class="emcefit-hero__brand">
                <span class="emcefit-hero__logo">eMCeFit</span>
                <span class="emcefit-hero__eyebrow">Studio Fitness</span>
            </div>

            <p class="emcefit-hero__tagline">
                Twój ruch po zdrowie. Twój ruch po życie w formie.
            </p>

            <p class="emcefit-hero__note">
                Zawsze jest odpowiedni czas na ruch w dobrym kierunku — w kierunku eMCeFit.
            </p>

            <Link :href="route('login')" class="emcefit-hero__cta"> Zaloguj się </Link>
        </div>
    </main>
</template>

<style scoped>
.emcefit-hero {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(2rem, 8vw, 6rem);
    background-color: #181310;
    color: #f2e7d6;
}

.emcefit-hero__inner {
    max-width: 40rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
}

.emcefit-hero__brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.emcefit-hero__logo {
    font-family: 'Cormorant Garamond', 'Georgia', serif;
    font-style: italic;
    font-weight: 500;
    font-size: clamp(3.25rem, 12vw, 5.5rem);
    line-height: 1;
    color: #d4af6a;
}

.emcefit-hero__eyebrow {
    text-transform: lowercase;
    letter-spacing: 0.42em;
    font-size: 0.72rem;
    color: rgba(212, 175, 106, 0.65);
    padding-left: 0.42em;
}

.emcefit-hero__tagline {
    font-size: clamp(1.15rem, 3.5vw, 1.6rem);
    line-height: 1.5;
    color: #f2e7d6;
    margin: 0;
}

.emcefit-hero__note {
    font-size: clamp(0.85rem, 2.4vw, 1rem);
    line-height: 1.6;
    color: rgba(242, 231, 214, 0.55);
    margin: 0;
}

.emcefit-hero__cta {
    margin-top: 0.5rem;
    display: inline-block;
    padding: 0.85rem 2.4rem;
    border-radius: 9999px;
    background-color: #d4af6a;
    color: #181310;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-decoration: none;
    transition:
        background-color 0.15s ease,
        transform 0.15s ease;
}

.emcefit-hero__cta:hover {
    background-color: #e3c489;
    transform: translateY(-1px);
}

.emcefit-hero__cta:focus-visible {
    outline: 2px solid #d4af6a;
    outline-offset: 3px;
}
</style>
