import React, { useEffect, useMemo, useState } from "react";
import { createRoot } from "react-dom/client";
import Snowfall from "react-snowfall";

function getPrefersReducedMotion() {
    if (typeof window === "undefined" || !window.matchMedia) return false;
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function SnowfallOverlay() {
    const prefersReducedMotion = useMemo(() => getPrefersReducedMotion(), []);

    // Default: ON, tapi user bisa matiin dari localStorage kalau kamu mau pakai nanti.
    const [enabled, setEnabled] = useState(() => {
        try {
            const saved = localStorage.getItem("snowfall_enabled");
            if (saved === null) return true;
            return saved === "1";
        } catch (e) {
            return true;
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem("snowfall_enabled", enabled ? "1" : "0");
        } catch (e) {
            // ignore
        }
    }, [enabled]);

    // Kalau user prefer reduced motion, otomatis OFF (lebih aksesibel)
    if (prefersReducedMotion) return null;
    if (!enabled) return null;

    return (
        <div
            id="snowfall-overlay"
            style={{
                position: "fixed",
                inset: 0,
                zIndex: 1020, // di atas page, tapi masih aman untuk modal (Bootstrap modal biasanya 1050)
                pointerEvents: "none",
            }}
            aria-hidden="true"
        >
            <Snowfall
                color="rgba(80, 120, 255, 0.35)"
                snowflakeCount={220}
                radius={[1.2, 3.2]}
                speed={[0.6, 1.8]}
                wind={[-0.2, 0.8]}
            />
        </div>
    );
}

function boot() {
    const el = document.getElementById("snowfall-root");
    if (!el) return;

    // Hindari double-mount kalau ada partial reload / hot
    if (el.dataset.mounted === "1") return;
    el.dataset.mounted = "1";

    const root = createRoot(el);
    root.render(<SnowfallOverlay />);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
