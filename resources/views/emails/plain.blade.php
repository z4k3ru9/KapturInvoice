{{--
    The body is one of two shapes:
    - Sanitized HTML typed into <x-editor> on the Email & Reminders settings
      page (App\Livewire\TallStackSettingsEmail) — server-sanitized via
      App\Support\Html\RichTextSanitizer on save, on top of the editor's own
      client-side sanitizer — rendered raw so formatting (bold, lists,
      links) survives into the sent email.
    - The hardcoded plain-text fallback a company hasn't customized yet
      (App\Services\BillingMailer::templateFor()), or a pre-migration
      plain-text value — never contains a tag, so it is escaped and its
      literal newlines preserved instead.
--}}
<div style="font-family: sans-serif; font-size: 14px; line-height: 1.6; color: #1f2937;">
    @if (str_contains($body, '<'))
        {!! $body !!}
    @else
        {!! nl2br(e($body)) !!}
    @endif
</div>
