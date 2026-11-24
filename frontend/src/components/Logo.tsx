interface LogoProps {
  size?: number
  showText?: boolean
  light?: boolean
}

/** HGKA crest: open book beneath a rising star, in navy / royal blue / gold. */
export function Logo({ size = 44, showText = true, light = false }: LogoProps) {
  return (
    <div className="flex items-center gap-3">
      <svg width={size} height={size} viewBox="0 0 64 64" role="img" aria-label="HGKA logo">
        <path d="M32 3 56 12v20c0 14-10 24-24 29C18 56 8 46 8 32V12z" fill="#102b50" />
        <path d="M32 8 51 15v17c0 11-8 19-19 24C21 51 13 43 13 32V15z" fill="none" stroke="#e5a52b" strokeWidth="1.5" />
        <path d="M32 40c-5-3-11-3-16-1V25c5-2 11-2 16 1 5-3 11-3 16-1v14c-5-2-11-2-16 1z" fill="#246bce" />
        <path d="M32 26v14" stroke="#fff" strokeWidth="1.5" />
        <path d="m32 11 2.2 4.6 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7z" fill="#e5a52b" />
      </svg>
      {showText && (
        <div className="leading-tight">
          <div className={`text-xl font-extrabold tracking-wide ${light ? 'text-white' : 'text-navy'}`}>HGKA</div>
          <div className={`text-[10px] uppercase tracking-widest ${light ? 'text-slate-300' : 'text-slate-500'}`}>
            His Grace Kiddies Academy
          </div>
        </div>
      )}
    </div>
  )
}
