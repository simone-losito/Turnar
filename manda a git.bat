@echo off
cd /d C:\xampp\htdocs\Turnar
echo =========================
echo PUSH TURNAR
echo =========================
git add .
git commit -m "update automatico"
git push
pause