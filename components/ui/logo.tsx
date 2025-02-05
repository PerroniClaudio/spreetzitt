function Logo() {
  return (
    <div className="flex items-center gap-2">
      <div className="w-8 h-8">
        <svg
          version="1.0"
          xmlns="http://www.w3.org/2000/svg"
          width="84.000000pt"
          height="84.000000pt"
          viewBox="0 0 84.000000 84.000000"
          preserveAspectRatio="xMidYMid meet"
          className="w-8 h-8 text-transparent"
          shapeRendering="geometricPrecision">
          <defs>
            <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop
                offset="0%"
                style={{ stopColor: "#FF7E5F", stopOpacity: 1 }}
              />
              <stop
                offset="100%"
                style={{ stopColor: "#FEB47B", stopOpacity: 1 }}
              />
            </linearGradient>
          </defs>
          <g
            transform="translate(0.000000,84.000000) scale(0.100000,-0.100000)"
            stroke="none">
            <path
              d="M196 724 c88 -64 167 -124 177 -133 17 -17 11 -23 -142 -135 l-161
              -118 0 -169 0 -169 362 0 c199 0 358 4 352 8 -5 5 -79 59 -164 122 -85 62
              -159 116 -163 120 -5 4 64 61 152 126 l161 119 0 173 0 172 -366 0 -366 0 158
              -116z m504 82 c0 -5 -259 -196 -274 -202 -9 -3 -241 160 -280 197 -6 5 104 9
              272 9 155 0 282 -2 282 -4z m40 -155 l0 -140 -312 -230 c-172 -127 -317 -231
              -321 -231 -4 0 -6 62 -5 137 l3 137 315 233 c173 128 316 233 318 233 1 0 2
              -63 2 -139z m-171 -521 l135 -100 -279 0 c-180 0 -276 3 -269 10 30 30 251
              188 264 189 8 0 75 -45 149 -99z"
              fill="url(#grad1)"
            />
          </g>
        </svg>
      </div>
      <span className="text-4xl font-thin subpixel-antialiased tracking-tight bg-gradient-to-r from-orange-400 to-[#E73029] text-transparent bg-clip-text">
        Spreetzitt
      </span>
    </div>
  );
}

export default Logo;
