import csv
import json


ID_COMMUNE = "75056"   # code de la commune, à demander à DATA
MODELE = "MRI_AGCM3_2_S"

with open("reponse.json", encoding="utf-8") as f:
    d = json.load(f)["daily"]

with open("series_exemple.csv", "w", newline="", encoding="utf-8") as out:
    w = csv.writer(out)
    w.writerow(["code_insee", "date", "t_min", "t_max", "t_moy", "precip", "modele"])
    for i, jour in enumerate(d["time"]):
        w.writerow([
            ID_COMMUNE,
            jour,
            d["temperature_2m_min"][i],
            d["temperature_2m_max"][i],
            d["temperature_2m_mean"][i],
            d["precipitation_sum"][i],
            MODELE,
        ])
print("Lignes écrites :", len(d["time"]))