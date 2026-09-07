import type { StoreCopy } from "@/content/store-copy";
import {
  CAMBODIA_LOCATIONS,
  getLocationLabel,
  getLocationValue,
  getProvinceLabel,
} from "@/data/cambodia-locations";
import type { DeliveryDetails, Language } from "@/types/store";

import styles from "./storefront.module.css";

type DeliverySelectorProps = {
  copy: StoreCopy;
  language: Language;
  value: DeliveryDetails;
  onChange: (value: DeliveryDetails) => void;
};

export function DeliverySelector({ copy, language, value, onChange }: DeliverySelectorProps) {
  const provinces = Object.keys(CAMBODIA_LOCATIONS);
  const districts = value.province ? CAMBODIA_LOCATIONS[value.province] : [];

  return (
    <div className={styles.deliveryFields}>
      <label>
        <span>{copy.province}</span>
        <select
          id="checkout-province"
          name="province"
          value={value.province}
          onChange={(event) =>
            onChange({ province: event.target.value, district: "", note: value.note })
          }
          required
        >
          <option value="">— {copy.province} —</option>
          {provinces.map((province) => (
            <option key={province} value={province}>
              {getProvinceLabel(province, language)}
            </option>
          ))}
        </select>
      </label>
      <label>
        <span>{copy.district}</span>
        <select
          id="checkout-district"
          name="district"
          value={value.district}
          onChange={(event) => onChange({ ...value, district: event.target.value })}
          disabled={!value.province}
          required
        >
          <option value="">— {copy.district} —</option>
          {districts.map((district) => (
            <option key={district} value={getLocationValue(district)}>
              {getLocationLabel(district, language)}
            </option>
          ))}
        </select>
      </label>
      <label className={styles.deliveryNote}>
        <span>{copy.deliveryNote}</span>
        <textarea
          id="checkout-delivery-note"
          name="deliveryNote"
          rows={3}
          value={value.note}
          onChange={(event) => onChange({ ...value, note: event.target.value })}
          placeholder={copy.deliveryNote}
        />
      </label>
    </div>
  );
}
