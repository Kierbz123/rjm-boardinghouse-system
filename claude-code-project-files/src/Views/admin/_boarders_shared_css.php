<?php // Form-field and section-headline styles shared by boarders.php (Add Boarder, Assign to Bed) and boarders_directory.php (edit drawer). ?>
<style>
/* Form Input Control Standards */
.field-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #555452;
    margin-bottom: 0.35rem;
}
.field-input {
    display: block;
    width: 100%;
    border: 1px solid #d4d2ce;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: #0a0a0a;
    background-color: #ffffff;
    box-sizing: border-box;
    transition: border-color 140ms ease, box-shadow 140ms ease;
}
.field-input:focus {
    outline: none;
    border-color: #bd6b36;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.field-select {
    display: block;
    width: 100%;
    border: 1px solid #d4d2ce;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: #0a0a0a;
    background-color: #ffffff;
    box-sizing: border-box;
    transition: border-color 140ms ease, box-shadow 140ms ease;
}
.field-select:focus {
    outline: none;
    border-color: #bd6b36;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Section Header Typography */
.section-headline-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 0.875rem;
    padding-bottom: 0.65rem;
    border-bottom: 1.5px solid #e6e5e2;
}
@media (min-width: 640px) {
    .section-headline-wrap {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.section-headline-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #0a0a0a;
    letter-spacing: -0.01em;
    margin: 0;
}
.section-headline-subtitle {
    font-size: 0.8125rem;
    color: #6b6b6b;
    margin-top: 0.15rem;
}
.count-badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #555452;
    background-color: #f1f0ee;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
    align-self: flex-start;
}
@media (min-width: 640px) {
    .count-badge-pill {
        align-self: center;
    }
}
</style>
