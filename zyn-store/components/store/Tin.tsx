import styles from "./storefront.module.css";

type TinProps = {
  color: string;
  accent?: string;
  size?: number;
  open?: boolean;
};

export function Tin({ color, accent = "#c9a227", size = 72, open = false }: TinProps) {
  return (
    <span
      className={styles.tin}
      style={{ width: size, height: size }}
      aria-hidden="true"
    >
      <svg width={size} height={size} viewBox="0 0 100 100" role="presentation">
        <circle cx="50" cy="50" r="47" fill={color} stroke={accent} strokeWidth="1" />
        <circle
          cx="50"
          cy="50"
          r="41"
          fill="none"
          stroke="rgba(201, 162, 39, 0.38)"
          strokeWidth="0.7"
        />
        <circle
          className={open ? styles.tinRingOpen : styles.tinRing}
          cx="50"
          cy="50"
          r="27"
          fill="none"
          stroke={accent}
          strokeWidth="1.2"
        />
        <circle cx="50" cy="50" r="3" fill={accent} opacity={open ? 1 : 0.55} />
      </svg>
    </span>
  );
}
