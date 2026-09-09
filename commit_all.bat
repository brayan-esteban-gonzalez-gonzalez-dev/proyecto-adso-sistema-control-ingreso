@echo off
git init
git add .gitignore
git commit -m "Add .gitignore"
FOR /F "tokens=*" %%A IN ('git ls-files --others --exclude-standard') DO (
    git add "%%A"
    git commit -m "Add %%A"
)
