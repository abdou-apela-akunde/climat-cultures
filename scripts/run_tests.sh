#!/bin/sh
# Lance tous les tests. Usage : sh scripts/run_tests.sh
# test_bdd a besoin d'une base MariaDB de TEST (variables DB_TEST_*) : sans elle il est IGNORÉ, pas réussi.
cd "$(dirname "$0")/.." || exit 1
status=0; ignores=0
for t in test_indicateurs test_client test_simulation test_bdd; do
  echo "== $t"
  out=$(php "scripts/$t.php"); code=$?
  case $code in
    0) ;;
    3) ignores=$((ignores+1)); echo "$out" ;;
    *) status=1 ;;
  esac
  echo "$out" | grep -E ' KO|OK, [0-9]+ KO|REFUSÉ'
done
if [ $status -ne 0 ]; then echo "ÉCHEC"
elif [ $ignores -gt 0 ]; then echo "TESTS PASSÉS, mais $ignores suite(s) ignorée(s) (pas de base de test)"
else echo "TOUS LES TESTS PASSENT"; fi
exit $status
