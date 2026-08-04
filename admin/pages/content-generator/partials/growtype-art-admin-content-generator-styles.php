<?php

defined('ABSPATH') || exit;

/**
 * Renders all CSS styles for the content generator page.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Styles::render()
 */
class Growtype_Art_Admin_Content_Generator_Styles
{
    public static function render(): void
    {
        ?>
        <style>
        /* ── Card ──────────────────────────────────────── */
        .gc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
        }

        /* ── Form row ──────────────────────────────────── */
        .gc-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .gc-form-row { grid-template-columns: 1fr; }
        }

        /* ── Field ─────────────────────────────────────── */
        .gc-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .gc-label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .gc-select,
        .gc-textarea {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            transition: border-color .15s, background .15s;
            width: 100%;
            box-sizing: border-box;
        }
        .gc-select { height: 38px; }
        .gc-select:focus,
        .gc-textarea:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }

        /* ── Character autocomplete ─────────────────────── */
        .gc-ac-wrap { position: relative; }
        .gc-input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            transition: border-color .15s, background .15s;
            width: 100%;
            box-sizing: border-box;
            height: 38px;
        }
        .gc-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .gc-ac-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.10);
            z-index: 9999;
            max-height: 240px;
            overflow-y: auto;
        }
        .gc-ac-item {
            padding: 9px 13px;
            font-size: 12px;
            color: #374151;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background .1s;
        }
        .gc-ac-item:last-child { border-bottom: none; }
        .gc-ac-item:hover,
        .gc-ac-item.active {
            background: #eef2ff;
            color: #4338ca;
        }

        /* ── Reference image preview ────────────────────── */
        .gc-label-hint {
            font-size: 11px;
            font-weight: 400;
            color: #94a3b8;
            margin-left: 6px;
        }
        .gc-ref-input-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .gc-ref-input-row .gc-input { flex: 1; }
        .gc-ref-browse-btn {
            flex-shrink: 0;
            white-space: nowrap;
            height: 38px;
            padding: 0 14px;
            font-size: 12px;
        }
        .gc-ref-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            margin-top: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .gc-ref-thumb {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .gc-ref-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }
        .gc-ref-label {
            font-size: 11px;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 260px;
        }
        .gc-ref-remove {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 5px;
            border: 1px solid #fca5a5;
            background: #fff1f2;
            color: #dc2626;
            cursor: pointer;
            transition: background .12s, border-color .12s;
            width: fit-content;
        }
        .gc-ref-remove:hover { background: #fee2e2; border-color: #ef4444; }

        .gc-textarea {
            resize: vertical;
            min-height: 130px;
            font-family: inherit;
            line-height: 1.6;
        }

        /* ── Actions ───────────────────────────────────── */
        .gc-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
        }
        .gc-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0 20px;
            height: 40px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .1s;
        }
        .gc-btn-primary:hover:not(:disabled) { background: #4f46e5; }
        .gc-btn-primary:active:not(:disabled) { transform: scale(.97); }
        .gc-btn-primary:disabled { opacity: .55; cursor: not-allowed; }
        .gc-btn-secondary {
            background: none;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 16px;
            height: 40px;
            font-size: 13px;
            color: #475569;
            cursor: pointer;
            transition: background .12s, border-color .12s;
        }
        .gc-btn-secondary:hover { background: #f1f5f9; border-color: #94a3b8; }
        .gc-btn-icon { font-size: 15px; }

        .gc-btn-icon-only {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            color: #64748b;
            transition: background .12s, border-color .12s;
        }
        .gc-btn-icon-only:hover { background: #f1f5f9; border-color: #94a3b8; }

        /* ── Result card ───────────────────────────────── */
        .gc-result-card { border-color: #c7d2fe; }
        .gc-result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .gc-result-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        .gc-result-meta {
            margin-left: 8px;
            font-size: 11px;
            color: #fff;
            background: #6366f1;
            padding: 2px 9px;
            border-radius: 50px;
            font-weight: 600;
        }
        .gc-result-content {
            font-size: 13px;
            line-height: 1.8;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 18px;
            max-height: 520px;
            overflow-y: auto;
        }
        .gc-word-count {
            margin-top: 8px;
            font-size: 11px;
            color: #94a3b8;
            text-align: right;
        }

        /* ── Loading overlay ───────────────────────────── */
        .gc-loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,.7);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #6366f1;
            font-weight: 600;
            gap: 14px;
        }
        .gc-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e0e7ff;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: gc-spin .7s linear infinite;
        }
        @keyframes gc-spin { to { transform: rotate(360deg); } }

        /* ── Content-type toggle ───────────────────────── */
        .gc-type-toggle {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .gc-type-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 18px;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            background: #fff;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s, box-shadow .15s;
            white-space: nowrap;
        }
        .gc-type-btn:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #334155;
        }
        .gc-type-btn.active {
            background: #6366f1;
            border-color: #6366f1;
            color: #fff;
            box-shadow: 0 2px 8px rgba(99,102,241,.25);
        }

        /* ── Media result grids ─────────────────────────── */
        .gc-result-media {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 8px 0;
        }
        .gc-result-img {
            max-width: 260px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            object-fit: cover;
            transition: transform .15s;
        }
        .gc-result-img:hover { transform: scale(1.02); }
        .gc-result-video {
            max-width: 420px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        /* ── Post-processing checkboxes ─────────────────── */
        .gc-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #475569;
            cursor: pointer;
            user-select: none;
        }
        .gc-checkbox-label input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #6366f1;
            cursor: pointer;
        }
        </style>
        <?php
    }
}
