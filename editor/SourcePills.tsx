import { __ } from '@wordpress/i18n';

export default function SourcePills({ urls }: { urls: string[] }) {
  if (!urls.length) { return null; }
  return (
    <div className="starter-ai__pills">
      <span style={{ marginRight: 4, fontSize: 11, color: '#646970' }}>
        {__('Sources:', 'starter-ai')}
      </span>
      {urls.map((url, i) => (
        <a key={`${url}-${i}`} className="starter-ai__pill" href={url} target="_blank" rel="noreferrer">
          {hostOf(url)}
        </a>
      ))}
    </div>
  );
}

function hostOf(url: string): string {
  try { return new URL(url).host.replace(/^www\./, ''); }
  catch { return url; }
}
