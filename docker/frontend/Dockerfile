FROM node:22-bookworm

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN chown -R node:node /app

EXPOSE 3000
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]
